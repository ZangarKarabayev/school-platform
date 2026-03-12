<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;

class EdsChallenge extends Model
{
    protected $fillable = [
        'challenge',
        'expires_at',
        'verified_at',
        'consumed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
