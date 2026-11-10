<?php

namespace Tests\Feature;

use App\Services\WahaService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WahaServiceTest extends TestCase
{
    private function configure(array $overrides = []): void
    {
        config([
            'services.waha.url' => 'http://waha.test:3000/',
            'services.waha.api_key' => 'super-secret-key',
            'services.waha.session' => 'default',
            'services.waha.timeout' => 5,
            'services.waha.retries' => 1,
            ...$overrides,
        ]);
    }

    #[Test]
    public function it_sends_a_text_message_with_the_expected_contract(): void
    {
        $this->configure();

        Http::fake([
            '*/api/sendText' => Http::response(['id' => 'wamid.HBgLMzQ4'], 200),
        ]);

        $result = app(WahaService::class)->sendText('201012345678@c.us', 'مرحبا');

        $this->assertTrue($result->ok);
        $this->assertSame('wamid.HBgLMzQ4', $result->messageId);
        $this->assertSame(200, $result->httpStatus);
        $this->assertNull($result->error);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://waha.test:3000/api/sendText'
                && $request['session'] === 'default'
                && $request['chatId'] === '201012345678@c.us'
                && $request['text'] === 'مرحبا'
                && $request['linkPreview'] === false
                && $request->hasHeader('X-Api-Key', 'super-secret-key');
        });
    }

    #[Test]
    public function it_honours_an_explicit_session_override(): void
    {
        $this->configure();

        Http::fake(['*/api/sendText' => Http::response(['id' => 'wamid.X'], 200)]);

        app(WahaService::class)->sendText('201012345678@c.us', 'مرحبا', 'attendance');

        Http::assertSent(fn (Request $request): bool => $request['session'] === 'attendance');
    }

    #[Test]
    public function it_surfaces_a_waha_error_message(): void
    {
        $this->configure();

        Http::fake([
            '*' => Http::response(['statusCode' => 401, 'error' => 'Unauthorized', 'message' => 'Invalid API key'], 401),
        ]);

        $result = app(WahaService::class)->sendText('201012345678@c.us', 'مرحبا');

        $this->assertFalse($result->ok);
        $this->assertSame(401, $result->httpStatus);
        $this->assertStringContainsString('Unauthorized', (string) $result->error);
        $this->assertStringContainsString('Invalid API key', (string) $result->error);
    }

    #[Test]
    public function it_rejects_a_success_response_without_a_message_id(): void
    {
        $this->configure();

        Http::fake(['*' => Http::response(['status' => 'queued'], 200)]);

        $result = app(WahaService::class)->sendText('201012345678@c.us', 'مرحبا');

        $this->assertFalse($result->ok, 'A 200 without an id cannot be matched to a message later.');
        $this->assertSame(200, $result->httpStatus);
    }

    #[Test]
    public function it_reports_a_transport_failure_without_throwing(): void
    {
        $this->configure();

        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $result = app(WahaService::class)->sendText('201012345678@c.us', 'مرحبا');

        $this->assertFalse($result->ok);
        $this->assertTrue($result->isTransportFailure());
        $this->assertNull($result->httpStatus);
    }

    #[Test]
    public function it_does_not_call_waha_when_credentials_are_missing(): void
    {
        $this->configure(['services.waha.api_key' => null]);

        Http::fake();

        $service = app(WahaService::class);

        $this->assertFalse($service->isConfigured());

        $result = $service->sendText('201012345678@c.us', 'مرحبا');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('WAHA_URL', (string) $result->error);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_reads_the_live_session_status(): void
    {
        $this->configure();

        Http::fake([
            '*/api/sessions/default' => Http::response([
                'status' => 'WORKING',
                'me' => ['id' => '201012345678@c.us', 'pushName' => 'WaHa'],
                'engine' => ['engine' => 'WEBJS'],
            ], 200),
        ]);

        $status = app(WahaService::class)->sessionStatus();

        $this->assertTrue($status['ok']);
        $this->assertSame('WORKING', $status['status']);
        $this->assertSame('WEBJS', $status['engine']);
        $this->assertSame('201012345678@c.us', $status['me']['id']);
        $this->assertNull($status['error']);
    }

    #[Test]
    public function it_reports_a_stopped_session(): void
    {
        $this->configure();

        Http::fake(['*' => Http::response(['status' => 'STOPPED', 'me' => null], 200)]);

        $status = app(WahaService::class)->sessionStatus();

        $this->assertTrue($status['ok'], 'A reachable WAHA that is merely stopped is still a working connection.');
        $this->assertSame('STOPPED', $status['status']);
        $this->assertNull($status['me']);
    }

    #[Test]
    public function it_masks_the_api_key_for_display(): void
    {
        $this->assertSame('supe...-key', WahaService::maskApiKey('super-secret-key'));
        $this->assertSame('****', WahaService::maskApiKey('abcd'));
        $this->assertNull(WahaService::maskApiKey(null));
        $this->assertNull(WahaService::maskApiKey('  '));
    }
}
