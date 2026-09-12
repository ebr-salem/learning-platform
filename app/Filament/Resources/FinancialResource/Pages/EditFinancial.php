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
     * Lock the assistant creator on edit: never allow changing
     * created_by via the form — keep the original value.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['created_by']);

        return $data;
    }
}
