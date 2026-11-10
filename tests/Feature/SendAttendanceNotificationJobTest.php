<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WhatsappLogSource;
use App\Enums\WhatsappLogStatus;
use App\Jobs\SendAttendanceNotificationJob;
use App\Models\Group;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Notifications\WhatsappSendFailed;
use App\Services\SmsMisrService;
use App\Services\WahaSendResult;
use App\Services\WahaService;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendAttendanceNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.waha.url' => 'http://waha.test:3000',
            'services.waha.api_key' => 'test-key',
            'services.waha.session' => 'default',
            'services.waha.default_country_code' => '20',
        ]);

        $this->student = User::factory()->create([
            'role' => UserRole::Student->value,
            'name' => 'أحمد محمد',
        ]);

        $this->student->studentProfile()->create([
            'group_id' => Group::factory()->create()->id,
            'student_code' => 'STU-1001',
            'grade' => 'الصف الأول',
            'qr_code_string' => 'qr-1001',
            'dob' => '2015-01-01',
            'guardian_name' => 'محمد الأب',
            'guardian_phone' => '01111111111',
        ]);
    }

    private function fakeWaha(bool $ok, ?string $error = null, ?string $messageId = 'wamid.ABC'): void
    {
        $service = $this->createMock(WahaService::class);
        $service->method('isConfigured')->willReturn(true);
        $service->method('sendText')->willReturnCallback(
            fn () => $ok
                ? WahaSendResult::sent($messageId, 200)
                : WahaSendResult::failed($error, 500)
        );

        $this->app->instance(WahaService::class, $service);
    }

    private function fakeSms(bool $sent): void
    {
        $sms = $this->createMock(SmsMisrService::class);
        $sms->method('send')->willReturn($sent);

        $this->app->instance(SmsMisrService::class, $sms);
    }

    /**
     * Run the job through the real queue dispatcher so its container-resolved
     * dependencies - and therefore the fakes above - are the ones exercised.
     */
    private function dispatchSync(SendAttendanceNotificationJob $job): void
    {
        Bus::dispatchSync($job);
    }

    #[Test]
    public function it_sends_whatsapp_and_skips_sms_when_delivery_succeeds(): void
    {
        Notification::fake();
        $this->fakeWaha(ok: true);
        $this->fakeSms(sent: false);

        $this->dispatchSync(new SendAttendanceNotificationJob($this->student));

        $log = WhatsappLog::sole();
        $this->assertSame(WhatsappLogStatus::Sent, $log->status);
        $this->assertSame(WhatsappLogSource::Auto, $log->source);
        $this->assertSame('201111111111@c.us', $log->chat_id);
        $this->assertSame('01111111111', $log->phone);
        $this->assertSame('wamid.ABC', $log->message_id);
        $this->assertFalse($log->sms_fallback_sent);
        $this->assertNull($log->error);
        $this->assertNotNull($log->sent_at);
        $this->assertTrue($log->student->is($this->student), 'The student relation must resolve through student_id.');

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_falls_back_to_sms_and_records_it_when_whatsapp_fails(): void
    {
        Notification::fake();
        $this->fakeWaha(ok: false, error: 'Session is not authenticated');
        $this->fakeSms(sent: true);

        $this->dispatchSync(new SendAttendanceNotificationJob($this->student));

        $log = WhatsappLog::sole();
        $this->assertSame(WhatsappLogStatus::Failed, $log->status);
        $this->assertSame('Session is not authenticated', $log->error);
        $this->assertTrue($log->sms_fallback_sent, 'The SMS rescue must be reflected on the WhatsApp log.');
        $this->assertNull($log->sent_at);

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_alerts_assistants_only_when_both_channels_fail(): void
    {
        Notification::fake();
        $this->fakeWaha(ok: false, error: 'Session is not authenticated');
        $this->fakeSms(sent: false);

        $assistant = User::factory()->create(['role' => UserRole::Assistant->value]);
        $other = User::factory()->create(['role' => UserRole::Student->value]);

        $this->dispatchSync(new SendAttendanceNotificationJob($this->student));

        $log = WhatsappLog::sole();
        $this->assertFalse($log->sms_fallback_sent);

        Notification::assertSentTo($assistant, WhatsappSendFailed::class);
        Notification::assertNotSentTo($other, WhatsappSendFailed::class);
    }

    #[Test]
    public function it_falls_back_to_sms_without_emitting_a_warning_when_the_number_cannot_be_normalised(): void
    {
        Notification::fake();

        $this->student->studentProfile()->update(['guardian_phone' => '123']);

        $sms = $this->createMock(SmsMisrService::class);
        $sms->expects($this->once())->method('send')->willReturn(true);
        $this->app->instance(SmsMisrService::class, $sms);

        $waha = $this->createMock(WahaService::class);
        $waha->expects($this->never())->method('sendText');
        $this->app->instance(WahaService::class, $waha);

        $this->dispatchSync(new SendAttendanceNotificationJob($this->student));

        $this->assertSame(0, WhatsappLog::count(), 'An unnormalisable number never reaches WAHA, so there is nothing to log.');
        Notification::assertNothingSent();
    }

    #[Test]
    public function it_does_nothing_when_the_guardian_has_no_phone(): void
    {
        Notification::fake();

        // A student with no profile at all is the same situation as a blank
        // guardian_phone: the job has nobody to contact.
        $profileLess = User::factory()->create(['role' => UserRole::Student->value]);
        $this->assertNull($profileLess->studentProfile);

        $this->fakeWaha(ok: true);
        $this->fakeSms(sent: false);

        $this->dispatchSync(new SendAttendanceNotificationJob($profileLess));

        $this->assertSame(0, WhatsappLog::count());
        Notification::assertNothingSent();
    }

    #[Test]
    public function it_builds_the_same_chat_id_the_service_expects(): void
    {
        $this->assertSame(
            WhatsappLog::defaultSession(),
            'default',
            'The log session must follow the configured WAHA session.'
        );

        $this->assertSame(
            '201111111111@c.us',
            PhoneNumber::toWhatsAppChatId($this->student->studentProfile->guardian_phone, '20')
        );
    }
}
