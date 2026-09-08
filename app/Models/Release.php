<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    protected $fillable = [
        'version_code',
        'version_name',
        'changelog',
        'file_name',
        'file_size',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function scopeLatestRelease(Builder $query): Builder
    {
        return $query->orderByDesc('published_at');
    }
}
