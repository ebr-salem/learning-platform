<?php

namespace App\Filament\Resources\FinancialResource\Pages;

use App\Filament\Resources\FinancialResource;
use Filament\Resources\Pages\EditRecord;

class EditFinancial extends EditRecord
{
    protected static string $resource = FinancialResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Split the stored title into the conditional inputs so the form
     * shows the month select for monthly records and the free-text
     * input for manual ("أخرى") records.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return FinancialResource::splitTitleData($data);
    }

    /**
     * Lock the assistant creator on edit: never allow changing
     * created_by via the form — keep the original value.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['created_by']);

        return FinancialResource::consolidateTitleData($data);
    }
}
