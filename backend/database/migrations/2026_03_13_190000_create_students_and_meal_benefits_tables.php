<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->string('iin', 12)->unique()->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('photo', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('student_number', 20)->nullable();
            $table->string('language', 10)->nullable();
            $table->unsignedSmallInteger('shift')->nullable();
            $table->string('school_year', 9)->nullable();
            $table->timestamps();
        });

        Schema::create('meal_benefits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamp('voucher_update_datetime')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_benefits');
        Schema::dropIfExists('students');
    }
};
