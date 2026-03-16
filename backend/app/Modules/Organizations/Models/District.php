<?php

namespace App\Modules\Organizations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = [
        'region_id',
        'name',
        'name_ru',
        'name_kk',
        'code',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $district): void {
            $district->name = $district->name_ru ?: $district->name_kk ?: $district->name;
        });
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'kk'
            ? ($this->name_kk ?: $this->name_ru ?: (string) $this->name)
            : ($this->name_ru ?: $this->name_kk ?: (string) $this->name);
    }
}