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
            self::TYPE_SCHOOL => 'Отчет по школе',
            self::TYPE_1_4 => 'Отчет по 1-4',
            self::TYPE_1_5_SUSN => 'Отчет по 1-5 СУСН',
            self::TYPE_5_11 => 'Отчет по 5-11',
            self::TYPE_5_11_SUSN => 'Отчет по 5-11 СУСН',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'В очереди',
            self::STATUS_COMPLETED => 'Готов',
            self::STATUS_FAILED => 'Ошибка',
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
