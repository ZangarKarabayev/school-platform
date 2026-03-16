<?php

namespace App\Modules\Organizations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = [
        'name',
        'name_ru',
        'name_kk',
        'code',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $region): void {
            $region->name = $region->name_ru ?: $region->name_kk ?: $region->name;
        });
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'kk'
            ? ($this->name_kk ?: $this->name_ru ?: (string) $this->name)
            : ($this->name_ru ?: $this->name_kk ?: (string) $this->name);
    }
}