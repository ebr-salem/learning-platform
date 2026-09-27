<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Enums\WhatsappLogSource;
use App\Filament\Concerns\SendsWhatsappMessages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Ad-hoc WhatsApp send for the occasions the automatic notifications do not
 * cover - a follow-up about fees, a lesson reminder, a direct apology to a
 * guardian. Picking a student fills in their guardian's number so the
 * assistant does not have to look it up, but any number can be typed instead.
 *
 * The form lives in content() because that is the schema a Filament page
 * renders, and it is read back through getState() so validation rules on the
 * fields actually run before anything reaches WAHA.
 */
class SendWhatsappMessage extends Page
{
    use SendsWhatsappMessages;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'إرسال رسالة واتساب';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $title = 'إرسال رسالة واتساب';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function content(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('الرسالة')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->description('اختر طالباً لتعبئة رقم ولي الأمر تلقائياً، أو اكتب الرقم مباشرة.')
                    ->columns(2)
                    ->schema([
                        Select::make('student_id')
                            ->label('الطالب')
                            ->searchable()
                            ->searchDebounce(500)
                            ->live()
                            ->options(fn (): array => static::studentOptions())
                            ->getSearchResultsUsing(fn (string $search): array => static::studentOptions($search))
                            ->helperText(fn (Get $get): ?string => static::guardianHint($get('student_id')))
                            ->afterStateUpdated(fn (Set $set, mixed $state) => $set('phone', static::guardianPhone($state))),
                        TextInput::make('phone')
                            ->label('رقم واتساب المستلم')
                            ->tel()
                            ->required()
                            ->maxLength(255)
                            ->helperText('يُقبل الرقم المحلي أو الدولي ويُحوَّل تلقائياً. مثال: 01012345678'),
                        Textarea::make('message')
                            ->label('نص الرسالة')
                            ->required()
                            ->rows(5)
                            ->maxLength(4096)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label('إرسال')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->action(fn () => $this->send()),
        ];
    }

    /**
     * Read the form back and put the message on the wire.
     *
     * Schema::getState() is what runs the field validation rules; the values
     * themselves are read from the page's own $data property, which is the
     * state path the schema is bound to.
     */
    private function send(): void
    {
        $this->getSchema('content')->getState();

        $phone = (string) ($this->data['phone'] ?? '');
        $message = (string) ($this->data['message'] ?? '');

        $student = filled($this->data['student_id'] ?? null)
            ? User::query()->with('studentProfile')->find($this->data['student_id'])
            : null;

        $this->sendWhatsappMessage(
            phone: $phone,
            message: $message,
            source: WhatsappLogSource::Manual,
            student: $student,
            recipientName: $student?->studentProfile?->guardian_name,
        );
    }

    /**
     * Students whose guardian has a number on file, labelled with the student
     * code so repeated names stay distinguishable. Capped per keystroke
     * because the platform is not large enough to need more.
     *
     * @return array<int, string>
     */
    protected static function studentOptions(?string $search = null): array
    {
        return User::query()
            ->where('role', UserRole::Student->value)
            ->whereHas('studentProfile', fn (Builder $query) => $query
                ->whereNotNull('guardian_phone')
                ->where('guardian_phone', '!=', ''),
            )
            ->when($search, fn (Builder $query) => $query->where(
                fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('studentProfile', fn (Builder $p) => $p->where('student_code', 'like', "%{$search}%")),
            ))
            ->with('studentProfile')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (User $student): array => [$student->id => static::studentOptionLabel($student)])
            ->all();
    }

    protected static function studentOptionLabel(User $student): string
    {
        $code = $student->studentProfile?->student_code;

        return filled($code) ? "{$student->name} — {$code}" : $student->name;
    }

    protected static function guardianPhone(mixed $studentId): ?string
    {
        if (blank($studentId)) {
            return null;
        }

        $phone = User::query()
            ->whereKey($studentId)
            ->value('studentProfile.guardian_phone');

        return filled($phone) ? (string) $phone : null;
    }

    /**
     * Explains what will happen once a student is picked, including the case
     * where the assistant has to type a number by hand.
     */
    protected static function guardianHint(mixed $studentId): ?string
    {
        if (blank($studentId)) {
            return null;
        }

        $phone = static::guardianPhone($studentId);

        return $phone !== null
            ? "رقم ولي الأمر: {$phone}"
            : 'لا يوجد رقم ولي أمر مسجل لهذا الطالب، اكتب الرقم يدوياً.';
    }
}
