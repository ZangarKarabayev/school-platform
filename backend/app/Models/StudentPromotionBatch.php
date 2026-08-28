<?php

namespace App\Models;

use App\Modules\Organizations\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentPromotionBatch extends Model
{
    protected $fillable = [
        'school_id',
        'created_by',
        'grade',
        'from_school_year',
        'to_school_year',
        'status',
        'total_count',
        'promoted_count',
        'graduated_count',
        'normalized_year_count',
        'skipped_count',
        'errors',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudentPromotionItem::class, 'batch_id');
    }
}
