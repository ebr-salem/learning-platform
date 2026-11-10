<?php

namespace App\Filament\Pages;

use App\Enums\WhatsappLogSource;
use App\Filament\Concerns\SendsWhatsappMessages;
use App\Services\WahaService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Read-only view of the WAHA credentials, plus the two things an admin
 * actually needs from a settings screen: is the instance reachable, and can
 * it deliver a message. The credentials themselves live in the environment
 * file, so changing them is a deploy-time edit rather than a dashboard one.
 */
class WhatsappSettings extends Page
{
    use SendsWhatsappMessages;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'إعدادات واتساب';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $title = 'إعدادات واتساب';

    public function content(Schema $schema): Schema
    {
        $waha = app(WahaService::class);
        $session = $waha->sessionStatus();

        return $schema
            ->components([
                Section::make('بيانات الاتصال')
                    ->description('تُقرأ من ملف البيئة ولا يمكن تعديلها من هذه الصفحة.')
                    ->icon(Heroicon::OutlinedServerStack)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('url')
                            ->label('رابط الخادم')
                            ->state($waha->baseUrl() ?: 'غير مُعد'),
                        TextEntry::make('api_key')
                            ->label('مفتاح API')
                            ->badge()
                            ->state(WahaService::maskApiKey($waha->apiKey()) ?: 'غير مُعد')
                            ->color($waha->isConfigured() ? 'gray' : 'danger'),
                        TextEntry::make('session')
                            ->label('اسم الجلسة')
                            ->state($waha->session()),
                        TextEntry::make('country_code')
                            ->label('مفتاح الدولة الافتراضي')
                            ->state('+'.$this->whatsappCountryCode()),
                    ]),

                Section::make('حالة الجلسة')
                    ->description('تُقرأ مباشرة من خادم WAHA.')
                    ->icon(Heroicon::OutlinedSignal)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reachability')
                            ->label('الاتصال بالخادم')
                            ->badge()
                            ->state($session['ok'] ? 'متصل' : 'غير متصل')
                            ->color($session['ok'] ? 'success' : 'danger'),
                        TextEntry::make('session_status')
                            ->label('حالة الجلسة')
                            ->badge()
                            ->state($session['status'] ?? ($session['ok'] ? 'غير معروف' : '—'))
                            ->color($this->sessionStatusColor($session)),
                        TextEntry::make('engine')
                            ->label('المحرك')
                            ->state($session['engine'] ?? '—'),
                        TextEntry::make('account')
                            ->label('الحساب المرتبط')
                            ->state($session['me']['pushName'] ?? $session['me']['id'] ?? '—'),
                        TextEntry::make('last_error')
                            ->label('آخر خطأ')
                            ->state($session['error'] ?? '—')
                            ->columnSpanFull(),
                    ]),

                Section::make('تسلسل الإرسال عند الفشل')
                    ->description('المعالجة المطبَّقة على إشعارات الحضور.')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('fallback')
                            ->hiddenLabel()
                            ->state(
                                "1. واتساب عبر WAHA، ويُسجَّل في سجل رسائل واتساب.\n".
                                "2. عند فشل واتساب، تُرسل رسالة SMS احتياطية عبر SMSMisr ويُعلَّم السجل بذلك.\n".
                                '3. عند فشل القناتين، يظهر تنبيه على جرس الإشعارات في لوحة التحكم.',
                            ),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('اختبار الاتصال')
                ->icon(Heroicon::OutlinedSignal)
                ->action(function (): void {
                    $session = app(WahaService::class)->sessionStatus();

                    if ($session['ok']) {
                        Notification::make()
                            ->success()
                            ->title('تم الاتصال بخادم WAHA بنجاح')
                            ->body("حالة الجلسة: {$session['status']}")
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('تعذّر الاتصال بخادم WAHA')
                        ->body($session['error'] ?? 'خطأ غير معروف')
                        ->persistent()
                        ->send();
                }),

            Action::make('sendTest')
                ->label('إرسال رسالة تجريبية')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->modalHeading('إرسال رسالة تجريبية')
                ->modalDescription('تُسجَّل هذه الرسالة في السجل بمصدر «تجريبي».')
                ->schema([
                    TextInput::make('phone')
                        ->label('رقم واتساب المستلم')
                        ->tel()
                        ->required()
                        ->default(fn (): string => (string) auth()->user()?->phone)
                        ->helperText('يُقبل الرقم المحلي أو الدولي ويُحوَّل تلقائياً. مثال: 01012345678'),
                    Textarea::make('message')
                        ->label('نص الرسالة')
                        ->required()
                        ->rows(3)
                        ->default('رسالة تجريبية من منصة التعلم'),
                ])
                ->action(function (array $data): void {
                    $this->sendWhatsappMessage(
                        phone: $data['phone'],
                        message: $data['message'],
                        source: WhatsappLogSource::Test,
                        recipientName: 'رسالة تجريبية',
                    );
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function sessionStatusColor(array $session): string
    {
        return match ($session['status']) {
            'WORKING' => 'success',
            'STARTING', 'SCAN_QR_CODE', 'PASSKEY_REQUIRED', 'PASSKEY_CONFIRMATION_REQUIRED' => 'warning',
            'FAILED' => 'danger',
            'STOPPED' => 'gray',
            default => $session['ok'] ? 'gray' : 'danger',
        };
    }
}
