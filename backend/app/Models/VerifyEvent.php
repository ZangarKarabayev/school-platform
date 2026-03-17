<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifyEvent extends Model
{
    protected $fillable = [
        'person_id',
        'name',
        'device_id',
        'verify_status',
        'create_time',
        'bin',
        'unique_qr',
    ];

    protected function casts(): array
    {
        return [
            'create_time' => 'datetime',
        ];
    }
}
