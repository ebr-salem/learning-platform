<?php

namespace App\Filament\Resources;

use App\Enums\WhatsappLogSource;
use App\Enums\WhatsappLogStatus;
use App\Filament\Resources\WhatsappLogResource\Pages;
use App\Models\WhatsappLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-only audit trail of every WhatsApp send. Answers the only two
 * questions that matter when a guardian says they did not get a message:
 * was it sent, and if not, what did WAHA say.
 */
class WhatsappLogResource extends Resource
{
    protected static ?string $model = WhatsappLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static ?string $modelLabel = 'رسالة واتساب';

    protected static ?string $pluralModelLabel = 'سجل رسائل واتساب';

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 3;

    /**
     * The log is evidence, not a working surface: nothing here is editable
     * or deletable, deliberately.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferFilters(false)
            ->columns([
                TextColumn::make('created_at')
                    ->label('الوقت')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->description(fn (?WhatsappLog $record): ?string => $record?->status === WhatsappLogStatus::Sent
                        ? null
                        : 'لم يُرسل'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->sortable(),

                TextColumn::make('source')
                    ->label('المصدر')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('student.name')
                    ->label('الطالب')
                    ->placeholder('—')
                    ->searchable(
                        query: fn (Builder $query, string $search) => $query->whereHas(
                            'student',
                            fn (Builder $q) => $q->where('name', 'like', "%{$search}%"),
                        ),
                    )
                    ->sortable(),

                TextColumn::make('chat_id')
                    ->label('رقم واتساب')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),
                TextColumn::make('message')
                    ->label('الرسالة')
                    ->limit(50)
                    ->wrap()
                    ->tooltip(fn (?WhatsappLog $record): string => (string) $record?->message),

                IconColumn::make('sms_fallback_sent')
                    ->label('SMS احتياطي')
                    ->boolean()
                    ->tooltip(fn (?WhatsappLog $record): string => (bool) $record?->sms_fallback_sent
                        ? 'نجحت رسالة SMS الاحتياطية'
                        : 'لم تُرسل رسالة SMS احتياطية'),

                // Always rendered rather than toggled with ->visible():
                // Filament evaluates a column's visibility without a record in
                // hand while the table sets itself up, and the error is exactly
                // what an assistant needs to see.
                TextColumn::make('error')
                    ->label('الخطأ')
                    ->limit(40)
                    ->color('danger')
                    ->placeholder('—')
                    ->tooltip(fn (?WhatsappLog $record): string => (string) $record?->error),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(WhatsappLogStatus::class)
                    ->multiple()
                    ->preload(),
                SelectFilter::make('source')
                    ->label('المصدر')
                    ->options(WhatsappLogSource::class)
                    ->multiple()
                    ->preload(),
                TernaryFilter::make('sms_fallback_sent')
                    ->label('تم إرسال SMS احتياطي'),
            ], layout: FiltersLayout::AboveContent)
            ->recordAction('details')
            ->recordActions([
                static::detailsAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Full record of one send: the raw WAHA response id, HTTP status, the
     * exact error text, and the session it went out on.
     */
    public static function detailsAction(string $name = 'details'): Action
    {
        return Action::make($name)
            ->label('عرض التفاصيل')
            ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->modalHeading(fn (WhatsappLog $record): string => 'تفاصيل الرسالة — '.($record->student?->name ?? $record->chat_id))
            ->modalContent(fn (WhatsappLog $record) => view('filament.resources.whatsapp-log-resource.partials.details', [
                'record' => $record,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsappLogs::route('/'),
        ];
    }
}
