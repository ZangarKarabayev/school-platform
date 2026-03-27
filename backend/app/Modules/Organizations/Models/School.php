<?php

namespace App\Modules\Organizations\Models;

use App\Models\Student;
use App\Models\Terminal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'kitchen_access_token',
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

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'school_id');
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class, 'school_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'kk'
            ? ($this->name_kk ?: $this->name_ru ?: (string) $this->name)
            : ($this->name_ru ?: $this->name_kk ?: (string) $this->name);
    }
}