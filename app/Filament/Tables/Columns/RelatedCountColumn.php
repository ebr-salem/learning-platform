<?php

namespace App\Filament\Tables\Columns;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class RelatedCountColumn extends TextColumn
{
    /**
     * Configure the column to count a relationship and open a modal
     * listing the related records' names when clicked.
     *
     * @param string $relationship  Relation method on the row model (e.g. 'groups', 'lessons').
     * @param string $displayColumn Column on the related model to list (e.g. 'name', 'title').
     * @param string $titleColumn   Column on the row model shown in the modal heading.
     * @param int    $limit         Max related names to list before truncating with a "+N more" note.
     */
    public function showsRelated(
        string $relationship,
        string $displayColumn = 'name',
        string $titleColumn = 'name',
        string $headingPrefix = '',
        ?string $emptyMessage = null,
        int $limit = 100,
    ): static {
        return $this
            ->counts($relationship)
            ->color('primary')
            ->extraCellAttributes([
                'style' => 'cursor:pointer',
                'onmouseover' => "this.style.textDecoration='underline';this.style.textUnderlineOffset='4px'",
                'onmouseout' => "this.style.textDecoration=''",
            ], merge: true)
            ->action(
                Action::make('view_' . $this->getName())
                    ->modalHeading(fn(Model $record): string => $headingPrefix . $record->getAttribute($titleColumn))
                    ->modalContent(fn(Model $record): HtmlString => $this->buildModalContent(
                        record: $record,
                        relationship: $relationship,
                        displayColumn: $displayColumn,
                        emptyMessage: $emptyMessage,
                        limit: $limit,
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('إغلاق')),
            );
    }

    /**
     * Build the modal body listing the related records' names.
     */
    protected function buildModalContent(
        Model $record,
        string $relationship,
        string $displayColumn,
        ?string $emptyMessage,
        int $limit,
    ): HtmlString {
        /** @var Collection<int, string> $names */
        $names = $record->{$relationship}()
            ->orderBy($displayColumn)
            ->limit($limit + 1)
            ->pluck($displayColumn);

        if ($names->isEmpty()) {
            return new HtmlString(
                '<p class="text-sm text-gray-500 dark:text-gray-400">'
                . e($emptyMessage ?? __('لا توجد عناصر مرتبطة.'))
                . '</p>'
            );
        }

        $hasMore = $names->count() > $limit;
        $visible = $names->take($limit);

        $items = $visible
            ->map(fn(string $name): string => '<li style="padding-block:2px">' . e($name) . '</li>')
            ->implode('');

        $list = '<ol style="list-style:decimal;padding-inline-start:1.5rem;font-size:0.875rem;line-height:1.5rem">' . $items . '</ol>';

        if ($hasMore) {
            $remaining = $names->count() - $limit;
            $list .= '<p class="mt-2 text-xs text-gray-500 dark:text-gray-400">'
                . e(__('و :count عنصر آخر...', ['count' => $remaining]))
                . '</p>';
        }

        return new HtmlString($list);
    }
}