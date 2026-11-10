<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WhatsappLogSource;
use App\Enums\WhatsappLogStatus;
use App\Filament\Pages\SendWhatsappMessage;
use App\Filament\Pages\WhatsappSettings;
use App\Filament\Resources\WhatsappLogResource;
use App\Filament\Resources\WhatsappLogResource\Pages\ListWhatsappLogs;
use App\Models\Group;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Notifications\WhatsappSendFailed;
use App\Services\WahaSendResult;
use App\Services\WahaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the dashboard surface of the WhatsApp feature: the three pages have
 * to render, the manual page has to read its form back out of the schema a
 * Filament page actually renders, and a failed send has to raise the
 * notification bell in the shape the panel expects.
 */
class WhatsappDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $assistant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.waha.url' => 'http://waha.test:3000',
            'services.waha.api_key' => 'test-key',
            'services.waha.session' => 'default',
            'services.waha.default_country_code' => '20',
        ]);

        // The settings page reads the live session status while rendering, so
        // nothing in this suite may reach the network.
        Http::preventStrayRequests();

        $this->assistant = User::factory()->create(['role' => UserRole::Assistant->value]);
    }

    #[Test]
    public function the_settings_page_renders_its_read_only_configuration(): void
    {
        Http::fake([
            '*/api/sessions/default' => Http::response([
                'status' => 'WORKING',
                'me' => ['id' => '201012345678@c.us', 'pushName' => 'WaHa'],
                'engine' => ['engine' => 'WEBJS'],
            ], 200),
        ]);

        $this->actingAs($this->assistant);

        $this->get(route('filament.admin.pages.whatsapp-settings'))
            ->assertOk()
            ->assertSee('WORKING');
    }

    #[Test]
    public function the_settings_page_survives_an_unreachable_waha(): void
    {
        // The status block calls WAHA on render, so a dead or unconfigured
        // server must not take the settings page down with it.
        config(['services.waha.api_key' => null]);

        $this->actingAs($this->assistant);

        $this->get(route('filament.admin.pages.whatsapp-settings'))->assertOk();
    }

    #[Test]
    public function the_manual_send_page_renders_its_fields(): void
    {
        $this->actingAs($this->assistant);

        $this->get(route('filament.admin.pages.send-whatsapp-message'))
            ->assertOk()
            ->assertSee('رقم واتساب المستلم')
            ->assertSee('نص الرسالة');
    }

    #[Test]
    public function the_manual_send_page_lists_students_with_a_guardian_number(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'name' => 'سارة إبراهيم',
        ]);

        $student->studentProfile()->create([
            'group_id' => Group::factory()->create()->id,
            'student_code' => 'STU-2002',
            'grade' => 'الصف الثاني',
            'qr_code_string' => 'qr-2002',
            'dob' => '2014-01-01',
            'guardian_name' => 'إبراهيم',
            'guardian_phone' => '01222222222',
        ]);

        $this->actingAs($this->assistant);

        $this->get(route('filament.admin.pages.send-whatsapp-message'))
            ->assertOk()
            ->assertSee('سارة إبراهيم')
            ->assertSee('STU-2002');
    }

    #[Test]
    public function sending_through_the_dashboard_writes_a_manual_log_entry(): void
    {
        Notification::fake();

        $waha = $this->createMock(WahaService::class);
        $waha->method('isConfigured')->willReturn(true);
        $waha->method('sendText')->willReturn(WahaSendResult::sent('wamid.MANUAL', 200));
        $this->app->instance(WahaService::class, $waha);

        Livewire::actingAs($this->assistant)
            ->test(SendWhatsappMessage::class)
            ->fillForm([
                'phone' => '01111111111',
                'message' => 'رسالة تجريبية من لوحة التحكم',
            ])
            ->callAction('send')
            ->assertHasNoFormErrors();

        $log = WhatsappLog::sole();

        $this->assertSame(WhatsappLogSource::Manual, $log->source);
        $this->assertSame(WhatsappLogStatus::Sent, $log->status);
        $this->assertSame('201111111111@c.us', $log->chat_id);
        $this->assertSame('رسالة تجريبية من لوحة التحكم', $log->message);
    }

    #[Test]
    public function the_manual_send_page_rejects_an_empty_message(): void
    {
        Livewire::actingAs($this->assistant)
            ->test(SendWhatsappMessage::class)
            ->fillForm([
                'phone' => '01111111111',
                'message' => '',
            ])
            ->callAction('send')
            ->assertHasFormErrors(['message' => 'required']);
    }

    #[Test]
    public function the_log_resource_lists_the_history(): void
    {
        $log = WhatsappLog::record(
            result: WahaSendResult::failed('Session is not authenticated', 500),
            chatId: '201111111111@c.us',
            phone: '01111111111',
            message: 'تم تسجيل حضور الطالب بنجاح',
            source: WhatsappLogSource::Auto,
        );

        $this->actingAs($this->assistant);

        $this->get(route('filament.admin.resources.whatsapp-logs.index'))->assertOk();

        Livewire::actingAs($this->assistant)
            ->test(ListWhatsappLogs::class)
            ->assertCanSeeTableRecords([$log])
            ->assertTableColumnExists('status')
            ->assertTableColumnExists('chat_id')
            ->assertTableColumnExists('error')
            ->assertTableColumnExists('sms_fallback_sent')
            ->searchTable('201111111111')
            ->assertCanSeeTableRecords([$log]);
    }

    #[Test]
    public function the_log_resource_refuses_to_create_or_delete(): void
    {
        $resource = WhatsappLogResource::class;

        $this->assertFalse($resource::canCreate());
        $this->assertFalse($resource::canEdit(WhatsappLog::record(
            result: WahaSendResult::sent('wamid.X'),
            chatId: '201111111111@c.us',
            message: 'x',
        )));
        $this->assertFalse($resource::canDelete(WhatsappLog::first() ?? new WhatsappLog));
    }

    #[Test]
    public function the_failure_notification_is_shaped_for_the_filament_bell(): void
    {
        $payload = (new WhatsappSendFailed(
            studentId: 7,
            studentName: 'أحمد محمد',
            reason: 'فشل الاتصال',
        ))->toArray($this->assistant);

        $this->assertSame('filament', $payload['format'], 'The panel only lists rows whose data carries this.');
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('body', $payload);
        $this->assertStringContainsString('أحمد محمد', $payload['body']);
        $this->assertStringContainsString('فشل الاتصال', $payload['body']);
        $this->assertSame(7, $payload['student_id']);
        $this->assertNotEmpty($payload['actions']);
    }

    #[Test]
    public function the_failure_notification_lands_in_the_database_for_the_bell(): void
    {
        $this->assistant->notify(new WhatsappSendFailed(
            studentId: 7,
            studentName: 'أحمد محمد',
            reason: 'فشل الاتصال',
        ));

        $row = $this->assistant->notifications()->sole();

        $this->assertSame(WhatsappSendFailed::class, $row->type);
        $this->assertNull($row->read_at);
        $this->assertSame('filament', $row->data['format']);
    }

    #[Test]
    public function the_settings_page_test_send_is_logged_as_a_test_message(): void
    {
        $waha = $this->createMock(WahaService::class);
        $waha->method('isConfigured')->willReturn(true);
        $waha->method('sendText')->willReturn(WahaSendResult::sent('wamid.TEST', 200));
        $waha->method('sessionStatus')->willReturn([
            'ok' => true,
            'status' => 'WORKING',
            'me' => ['id' => '201012345678@c.us', 'pushName' => 'WaHa'],
            'engine' => 'WEBJS',
            'error' => null,
            'http_status' => 200,
        ]);
        $this->app->instance(WahaService::class, $waha);

        Livewire::actingAs($this->assistant)
            ->test(WhatsappSettings::class)
            ->callAction('sendTest', data: [
                'phone' => '01012345678',
                'message' => 'رسالة تجريبية',
            ]);

        $log = WhatsappLog::sole();

        $this->assertSame(WhatsappLogSource::Test, $log->source);
        $this->assertSame(WhatsappLogStatus::Sent, $log->status);
        $this->assertSame('201012345678@c.us', $log->chat_id);
    }
}
