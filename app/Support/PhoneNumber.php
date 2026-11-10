<?php

namespace App\Support;

/**
 * Turns a phone number as stored by the app into a WAHA chat id.
 *
 * WAHA addresses contacts as "<international number without +>@c.us", while
 * this app stores local numbers ("01111111111"), international ones
 * ("+201111111111", "0020111111111") and occasionally a ready-made chat id.
 * Every format is accepted here so callers never have to care which one a
 * given guardian was saved with.
 */
class PhoneNumber
{
    /**
     * Suffixes WAHA understands. A value that already carries one of them
     * is passed through untouched.
     *
     * @var list<string>
     */
    private const CHAT_SUFFIXES = ['@c.us', '@g.us', '@broadcast', '@newsletter', '@lid'];

    /**
     * ITU-T E.164 caps an international number at 15 digits.
     */
    private const MIN_DIGITS = 8;

    private const MAX_DIGITS = 15;

    /**
     * Build the WAHA chat id for a phone number, or null when the number
     * cannot be interpreted.
     */
    public static function toWhatsAppChatId(?string $phone, string $defaultCountryCode = '20'): ?string
    {
        $raw = strtolower(trim((string) $phone));

        // A non-contact target (group, broadcast, newsletter, lid) is already
        // a complete address - rewriting its suffix to @c.us would point the
        // message at a contact that does not exist.
        foreach (self::CHAT_SUFFIXES as $suffix) {
            if ($suffix !== '@c.us' && str_ends_with($raw, $suffix)) {
                return $raw;
            }
        }

        $chatId = self::toInternational($raw, $defaultCountryCode);

        if ($chatId === null) {
            return null;
        }

        return $chatId.'@c.us';
    }

    /**
     * Normalise a phone number to bare international digits (no "+", no
     * chat id suffix), or null when it cannot be interpreted.
     */
    public static function toInternational(?string $phone, string $defaultCountryCode = '20'): ?string
    {
        $raw = strtolower(trim((string) $phone));

        if ($raw === '') {
            return null;
        }

        foreach (self::CHAT_SUFFIXES as $suffix) {
            if (str_ends_with($raw, $suffix)) {
                $raw = substr($raw, 0, -strlen($suffix));
                break;
            }
        }

        // Keep digits only: drops spaces, dashes, dots, slashes and parentheses.
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        $countryCode = preg_replace('/\D+/', '', $defaultCountryCode) ?? '';

        if (str_starts_with($digits, '00')) {
            // "0020..." is already international - drop the international prefix.
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && $countryCode !== '') {
            // Local trunk-prefixed form: "01..." / "011..." -> prepend the country code.
            $digits = $countryCode.substr($digits, 1);
        }

        if (strlen($digits) < self::MIN_DIGITS || strlen($digits) > self::MAX_DIGITS) {
            return null;
        }

        return $digits;
    }

    /**
     * Render a chat id back into a human readable phone number for the UI.
     */
    public static function fromChatId(?string $chatId): ?string
    {
        $chatId = strtolower(trim((string) $chatId));

        if ($chatId === '') {
            return null;
        }

        foreach (self::CHAT_SUFFIXES as $suffix) {
            if (str_ends_with($chatId, $suffix)) {
                return substr($chatId, 0, -strlen($suffix));
            }
        }

        return $chatId;
    }
}
