<?php

namespace App\Models;

use App\Modules\Organizations\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    public const STATUS_CURRENT = 'current';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_GRADUATED = 'graduated';

    protected $fillable = [
        'student_id',
        'school_id',
        'classroom_id',
        'school_year',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'classroom_id');
    }
}
