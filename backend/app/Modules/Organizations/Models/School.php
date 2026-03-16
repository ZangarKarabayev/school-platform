<?php

namespace App\Modules\Organizations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class School extends Model
{
    protected $fillable = [
        'district_id',
        'name',
        'name_ru',
        'name_kk',
        'code',
        'bin',
        'address',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $school): void {
            $school->name = $school->name_ru ?: $school->name_kk ?: $school->name;
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'kk'
            ? ($this->name_kk ?: $this->name_ru ?: (string) $this->name)
            : ($this->name_ru ?: $this->name_kk ?: (string) $this->name);
    }
}