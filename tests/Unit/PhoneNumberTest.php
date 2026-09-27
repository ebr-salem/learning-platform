<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function chatIdCases(): array
    {
        return [
            'local mobile' => ['01111111111', '201111111111@c.us'],
            'local with spaces' => ['010 1234 5678', '201012345678@c.us'],
            'local with dashes' => ['010-1234-5678', '201012345678@c.us'],
            'international with plus' => ['+201111111111', '201111111111@c.us'],
            'international double zero' => ['00201111111111', '201111111111@c.us'],
            'already a chat id' => ['201111111111@c.us', '201111111111@c.us'],
            'uppercase chat id' => ['201111111111@C.US', '201111111111@c.us'],
            'null' => [null, null],
            'empty' => ['', null],
            'only separators' => ['   ', null],
            'too short' => ['12345', null],
            'too long' => ['1234567890123456789', null],
        ];
    }

    #[DataProvider('chatIdCases')]
    public function test_it_builds_a_waha_chat_id(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toWhatsAppChatId($input));
    }

    public function test_it_respects_a_custom_country_code(): void
    {
        $this->assertSame('9661012345678@c.us', PhoneNumber::toWhatsAppChatId('01012345678', '966'));
    }

    /**
     * A group or broadcast address already names its target, so rewriting its
     * suffix would send the message to a contact that does not exist.
     */
    #[DataProvider('nonContactChatIdCases')]
    public function test_it_passes_non_contact_chat_ids_through(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toWhatsAppChatId($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function nonContactChatIdCases(): array
    {
        return [
            'group' => ['120363000000000000@g.us', '120363000000000000@g.us'],
            'broadcast' => ['002012345678@broadcast', '002012345678@broadcast'],
            'newsletter' => ['120363111111111111@newsletter', '120363111111111111@newsletter'],
            'lid' => ['123456789012345@lid', '123456789012345@lid'],
        ];
    }

    #[DataProvider('internationalCases')]
    public function test_it_normalises_to_bare_international_digits(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toInternational($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function internationalCases(): array
    {
        return [
            'local' => ['01111111111', '201111111111'],
            'international' => ['+201111111111', '201111111111'],
            'chat id stripped' => ['201111111111@c.us', '201111111111'],
            'bracketed' => ['+20 (111) 111-1111', '201111111111'],
        ];
    }

    public function test_it_renders_a_chat_id_back_to_a_number(): void
    {
        $this->assertSame('201111111111', PhoneNumber::fromChatId('201111111111@c.us'));
        $this->assertSame('120363000000000000', PhoneNumber::fromChatId('120363000000000000@g.us'));
        $this->assertNull(PhoneNumber::fromChatId(''));
    }
}
