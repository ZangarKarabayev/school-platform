<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthIdentity extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'phone',
        'bin',
        'certificate_serial',
        'certificate_thumbprint',
        'subject_dn',
        'issuer_dn',
        'valid_from',
        'valid_to',
        'last_verified_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'last_verified_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
