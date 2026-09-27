<?php

namespace App\Services;

/**
 * The outcome of a single outbound WhatsApp call.
 *
 * WAHA signals trouble in several different ways - a non-2xx status, a JSON
 * body carrying an "error" field, or a transport level timeout - and the log
 * table needs all of it, so the result keeps the raw status and body rather
 * than collapsing everything into a boolean.
 */
final readonly class WahaSendResult
{
    private function __construct(
        public bool $ok,
        public ?string $messageId = null,
        public ?string $error = null,
        public ?int $httpStatus = null,
    ) {}

    public static function sent(?string $messageId, ?int $httpStatus = null): self
    {
        return new self(true, $messageId, null, $httpStatus);
    }

    public static function failed(string $error, ?int $httpStatus = null): self
    {
        return new self(false, null, $error, $httpStatus);
    }

    /**
     * True when the request never made it to WAHA (DNS, connection refused,
     * timeout, TLS). Worth retrying, unlike a rejected request.
     */
    public function isTransportFailure(): bool
    {
        return ! $this->ok && $this->httpStatus === null;
    }
}
