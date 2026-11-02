<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentParent extends Model
{
    protected $table = 'parents';

    protected $fillable = ['full_name', 'iin', 'phone', 'gender'];

    protected static function booted(): void
    {
        static::saving(function (self $parent): void {
            $digits = preg_replace('/\D/', '', (string) $parent->phone);
            if (strlen($digits) === 11 && in_array($digits[0], ['7', '8'], true)) {
                $parent->phone = '+7'.substr($digits, 1);
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
