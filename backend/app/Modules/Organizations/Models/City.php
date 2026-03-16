<?php

namespace App\Modules\Organizations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    protected $fillable = [
        'district_id',
        'name',
        'name_ru',
        'name_kk',
        'code',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $city): void {
            $city->name = $city->name_ru ?: $city->name_kk ?: $city->name;
        });
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