<?php

namespace App\Filament\Resources\FinancialResource\Pages;

use App\Filament\Resources\FinancialResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinancial extends CreateRecord
{
    protected static string $resource = FinancialResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Authoritative auto-fill of the assistant creator on create.
     * The form Hidden field is non-editable, but this guarantees
     * created_by is always the authenticated assistant even if
     * the hidden input is missing/tampered with.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['created_by'] ?? null)) {
            $data['created_by'] = auth()->id();
        }

        return $data;
    }
}
