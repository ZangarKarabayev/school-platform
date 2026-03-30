<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'student_id',
        'dish_id',
        'order_date',
        'order_time',
        'status',
        'transaction_status',
        'transaction_error',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'transaction_status' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }
}
