<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'student_id',
        'dish_id',
        'created_by_user_id',
        'created_by_terminal_id',
        'order_date',
        'order_time',
        'status',
        'completed_at',
        'transaction_status',
        'transaction_error',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'completed_at' => 'datetime',
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

    public function creatorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function creatorTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'created_by_terminal_id');
    }
}
