<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPromotionItem extends Model
{
    protected $fillable = [
        'batch_id',
        'student_id',
        'old_classroom_id',
        'new_classroom_id',
        'old_school_year',
        'new_school_year',
        'old_status',
        'new_status',
        'result',
        'error',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StudentPromotionBatch::class, 'batch_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function oldClassroom(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'old_classroom_id');
    }

    public function newClassroom(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'new_classroom_id');
    }
}
