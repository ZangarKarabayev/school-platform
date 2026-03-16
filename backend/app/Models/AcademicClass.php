<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicClass extends Model
{
    protected $table = 'classrooms';

    public $timestamps = false;

    protected $fillable = [
        'grade',
        'letter',
        'full_name',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $academicClass): void {
            $academicClass->letter = mb_strtoupper(trim($academicClass->letter));
            $academicClass->full_name = $academicClass->grade . $academicClass->letter;
        });
    }
}