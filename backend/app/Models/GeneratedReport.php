<?php

namespace App\Models;

use App\Modules\Organizations\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedReport extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_SCHOOL = 'school';
    public const TYPE_1_4 = '1_4';
    public const TYPE_1_5_SUSN = '1_5_susn';
    public const TYPE_5_11 = '5_11';
    public const TYPE_5_11_SUSN = '5_11_susn';

    protected $fillable = [
        'user_id',
        'school_id',
        'report_type',
        'date_from',
        'date_to',
        'status',
        'file_disk',
        'file_path',
        'error_message',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_SCHOOL => __('ui.reports_page.type_school'),
            self::TYPE_1_4 => __('ui.reports_page.type_1_4'),
            self::TYPE_1_5_SUSN => __('ui.reports_page.type_1_5_susn'),
            self::TYPE_5_11 => __('ui.reports_page.type_5_11'),
            self::TYPE_5_11_SUSN => __('ui.reports_page.type_5_11_susn'),
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => __('ui.reports_page.status_pending'),
            self::STATUS_COMPLETED => __('ui.reports_page.status_completed'),
            self::STATUS_FAILED => __('ui.reports_page.status_failed'),
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeOptions()[$this->report_type] ?? $this->report_type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }
}
