<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        return [
            'students' => Tab::make('الطلاب')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('role', UserRole::Student->value))
                ->badge(fn(): int => User::query()->where('role', UserRole::Student->value)->count()),

            // 'assistants' => Tab::make('المساعدين')
            //     ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('role', UserRole::Assistant->value))
            //     ->badge(fn (): int => User::query()->where('role', UserRole::Assistant->value)->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'students';
    }
}