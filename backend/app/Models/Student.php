<?php

namespace App\Models;

use App\Modules\Organizations\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Student extends Model
{
    public const ORDER_ELIGIBLE_BENEFIT_TYPES = ['susn', 'voucher'];

    protected $fillable = [
        'iin',
        'first_name',
        'last_name',
        'middle_name',
        'birth_date',
        'gender',
        'classroom_id',
        'classroom_history',
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
            'classroom_history' => 'array',
            'photo_updated_at' => 'datetime',
            'photo_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $student): void {
            if ((! $student->exists && $student->classroom_id !== null)
                || ($student->exists && $student->isDirty('classroom_id') && $student->classroom_id !== null)) {
                $history = array_values($student->classroom_history ?? []);
                $classroomId = (int) $student->classroom_id;

                if ($history === [] || (int) end($history) !== $classroomId) {
                    $history[] = $classroomId;
                    $student->classroom_history = $history;
                }
            }

            if (! $student->isDirty('photo')) {
                return;
            }

            $student->photo_updated_at = $student->photo ? now() : null;
            $student->photo_synced_at = null;
        });

        static::updated(function (self $student): void {
            if (! $student->wasChanged('photo')) {
                return;
            }

            $originalPhoto = $student->getOriginal('photo');

            if (filled($originalPhoto) && $originalPhoto !== $student->photo) {
                Storage::disk('public')->delete($originalPhoto);
            }
        });

        static::deleted(function (self $student): void {
            if (filled($student->photo)) {
                Storage::disk('public')->delete($student->photo);
            }
        });

        static::saved(function (self $student): void {
            $previous = $student->getPrevious();
            $previousSchoolYear = $previous['school_year'] ?? null;

            if (filled($previousSchoolYear) && $previousSchoolYear !== $student->school_year) {
                $student->enrollments()
                    ->where('school_year', $previousSchoolYear)
                    ->where('status', StudentEnrollment::STATUS_CURRENT)
                    ->update([
                        'status' => StudentEnrollment::STATUS_COMPLETED,
                        'ended_at' => now()->toDateString(),
                    ]);
            }

            if ($student->classroom_id === null && $student->wasChanged('classroom_id') && filled($student->school_year)) {
                $student->enrollments()
                    ->where('school_year', $student->school_year)
                    ->where('status', StudentEnrollment::STATUS_CURRENT)
                    ->update([
                        'status' => StudentEnrollment::STATUS_COMPLETED,
                        'ended_at' => now()->toDateString(),
                    ]);
            }

            if (blank($student->school_year) || $student->classroom_id === null) {
                return;
            }

            StudentEnrollment::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'school_year' => $student->school_year,
                ],
                [
                    'school_id' => $student->school_id,
                    'classroom_id' => $student->classroom_id,
                    'status' => StudentEnrollment::STATUS_CURRENT,
                    'ended_at' => null,
                ],
            );
        });
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

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)
            ->where('status', StudentEnrollment::STATUS_CURRENT)
            ->latestOfMany();
    }

    public function latestMealBenefit(): HasOne
    {
        return $this->hasOne(MealBenefit::class)->latestOfMany();
    }

    public function scopeEligibleForOrder(Builder $query): Builder
    {
        return $query->whereHas('latestMealBenefit', function (Builder $benefitQuery): void {
            $benefitQuery->whereIn('type', self::ORDER_ELIGIBLE_BENEFIT_TYPES);
        });
    }

    public function canCreateOrder(): bool
    {
        return in_array($this->latestMealBenefit?->type, self::ORDER_ELIGIBLE_BENEFIT_TYPES, true);
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->last_name,
            $this->first_name,
            $this->middle_name,
        ])));
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (blank($this->photo)) {
            return null;
        }

        return Storage::disk('public')->url($this->photo);
    }
}
