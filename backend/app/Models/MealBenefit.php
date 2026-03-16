<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealBenefit extends Model
{
    public const TYPES = [
        'susn',
        'voucher',
        'paid',
    ];

    protected $fillable = [
        'student_id',
        'type',
        'voucher_update_datetime',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'voucher_update_datetime' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
