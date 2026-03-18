<?php

namespace App\Models;

use App\Modules\Organizations\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    protected $fillable = [
        'iin',
        'first_name',
        'last_name',
        'middle_name',
        'birth_date',
        'gender',
        'classroom_id',
        'school_id',
        'phone',
        'address',
        'photo',
        'photo_updated_at',
        'photo_synced_at',
        'status',
        'student_number',
        'language',
        'shift',
        'school_year',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'photo_updated_at' => 'datetime',
            'photo_synced_at' => 'datetime',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'classroom_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function mealBenefits(): HasMany
    {
        return $this->hasMany(MealBenefit::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function latestMealBenefit(): HasOne
    {
        return $this->hasOne(MealBenefit::class)->latestOfMany();
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->last_name,
            $this->first_name,
            $this->middle_name,
        ])));
    }
}
