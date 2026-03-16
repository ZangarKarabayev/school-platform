<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dish extends Model
{
    protected $fillable = [
        'name',
        'category',
        'calories',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'calories' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
