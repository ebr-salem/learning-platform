<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for a self-hosted WAHA (WhatsApp HTTP API) instance.
 *
 * Every WAHA call is authenticated with the "X-Api-Key" header and, unless the
 * caller supplies one, targets the session named in config. When credentials
 * are missing the service degrades to a no-op that reports a failure instead
 * of throwing, so local development and un-configured deployments never
 * attempt a network call.
 */
class WahaService
{
    /**
     * Cap on the response body copied into the log, so a verbose WAHA error
     * page cannot bloat the whatsapp_logs table.
     */
    private const MAX_BODY_LENGTH = 1000;

    /**
     * Whether a base URL and API key are available to talk to WAHA.
     */
    public function isConfigured(): bool
    {
        return filled($this->baseUrl()) && filled($this->apiKey());
    }

    /**
     * Send a plain text message to a chat.
     *
     * @param  string  $chatId  A WAHA chat id, e.g. "201012345678@c.us". Use
     *                          {@see PhoneNumber::toWhatsAppChatId()} to build one.
     */
    public function sendText(string $chatId, string $text, ?string $session = null): WahaSendResult
    {
        if (! $this->isConfigured()) {
            Log::info('WAHA skipped: credentials not configured.', [
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            return WahaSendResult::failed('لم يتم إعداد بيانات WAHA (WAHA_URL / WAHA_API_KEY).');
        }

        $response = $this->request('post', 'api/sendText', [
            'session' => $session ?? $this->session(),
            'chatId' => $chatId,
            'text' => $text,
            'linkPreview' => false,
        ]);

        if ($response === null) {
            return WahaSendResult::failed('تعذّر الاتصال بخادم WAHA.');
        }

        if ($response->failed()) {
            return WahaSendResult::failed($this->errorMessage($response), $response->status());
        }

        $messageId = $response->json('id');

        if (! is_string($messageId) || $messageId === '') {
            return WahaSendResult::failed('استجابة WAHA غير متوقعة: '.$this->bodyExcerpt($response), $response->status());
        }

        return WahaSendResult::sent($messageId, $response->status());
    }

    /**
     * Read the live status of a WAHA session. Powers the read-only status
     * block on the dashboard settings page.
     *
     * @return array{ok: bool, status: ?string, me: ?array<string, mixed>, engine: ?string, error: ?string, http_status: ?int}
     */
    public function sessionStatus(?string $session = null): array
    {
        $failure = [
            'ok' => false,
            'status' => null,
            'me' => null,
            'engine' => null,
            'error' => 'لم يتم إعداد بيانات WAHA (WAHA_URL / WAHA_API_KEY).',
            'http_status' => null,
        ];

        if (! $this->isConfigured()) {
            return $failure;
        }

        $response = $this->request('get', 'api/sessions/'.rawurlencode($session ?? $this->session()));

        if ($response === null) {
            return [...$failure, 'error' => 'تعذّر الاتصال بخادم WAHA.'];
        }

        if ($response->failed()) {
            return [
                ...$failure,
                'error' => $this->errorMessage($response),
                'http_status' => $response->status(),
            ];
        }

        $me = $response->json('me');

        return [
            'ok' => true,
            'status' => is_string($response->json('status')) ? $response->json('status') : null,
            'me' => is_array($me) ? $me : null,
            'engine' => is_string($response->json('engine.engine')) ? $response->json('engine.engine') : null,
            'error' => null,
            'http_status' => $response->status(),
        ];
    }

    /**
     * The session name this service sends from.
     */
    public function session(): string
    {
        return (string) (config('services.waha.session') ?: 'default');
    }

    /**
     * The configured base URL, without a trailing slash.
     */
    public function baseUrl(): string
    {
        return rtrim((string) config('services.waha.url'), '/');
    }

    /**
     * The raw API key, for the send log and the dashboard status block.
     */
    public function apiKey(): ?string
    {
        return config('services.waha.api_key');
    }

    /**
     * Mask an API key for display: "abcd...wxyz".
     */
    public static function maskApiKey(?string $apiKey): ?string
    {
        $apiKey = trim((string) $apiKey);

        if ($apiKey === '') {
            return null;
        }

        if (strlen($apiKey) <= 8) {
            return str_repeat('*', strlen($apiKey));
        }

        return substr($apiKey, 0, 4).'...'.substr($apiKey, -4);
    }

    /**
     * Perform an authenticated request, retrying only transport level
     * failures. API level rejections (bad key, unknown session, blocked
     * number) are answers, not blips, so replaying them would only burn the
     * WhatsApp rate limit.
     *
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $path, array $payload = []): ?Response
    {
        try {
            /** @var Response $response */
            $response = $this->client()
                ->retry(
                    max(1, (int) config('services.waha.retries', 2)),
                    250,
                    throw: false,
                )
                ->{$method}($this->endpoint($path), $payload);

            return $response;
        } catch (ConnectionException $exception) {
            Log::error('WAHA connection failed.', [
                'path' => $path,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * A pre-configured, authenticated HTTP client. Every WAHA call goes
     * through here so the auth header and timeout live in one place.
     */
    public function client(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(['X-Api-Key' => (string) $this->apiKey()])
            ->timeout((int) config('services.waha.timeout', 15));
    }

    private function endpoint(string $path): string
    {
        return $this->baseUrl().'/'.ltrim($path, '/');
    }

    /**
     * Pull a human readable message out of a WAHA error body. WAHA returns
     * {"statusCode":..,"error":..,"message":..} for rejections.
     */
    private function errorMessage(Response $response): string
    {
        $parts = array_filter([
            is_string($response->json('error')) ? $response->json('error') : null,
            is_string($response->json('message')) ? $response->json('message') : null,
        ]);

        if ($parts === []) {
            return 'فشل الطلب (HTTP '.$response->status().'): '.$this->bodyExcerpt($response);
        }

        return implode(' - ', $parts).' (HTTP '.$response->status().')';
    }

    private function bodyExcerpt(Response $response): string
    {
        $body = trim($response->body());

        if ($body === '') {
            return '(استجابة فارغة)';
        }

        return mb_strlen($body) > self::MAX_BODY_LENGTH
            ? mb_substr($body, 0, self::MAX_BODY_LENGTH).'…'
            : $body;
    }
}
