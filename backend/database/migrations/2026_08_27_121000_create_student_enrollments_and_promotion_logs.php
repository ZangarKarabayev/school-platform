<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->unique(['grade', 'letter'], 'classrooms_grade_letter_unique');
        });

        Schema::create('student_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->string('school_year', 9);
            $table->string('status', 20)->default('current');
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'school_year']);
            $table->index(['school_id', 'school_year', 'status']);
            $table->index(['classroom_id', 'school_year']);
        });

        Schema::create('student_promotion_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('grade');
            $table->string('from_school_year', 9);
            $table->string('to_school_year', 9);
            $table->string('status', 20)->default('processing');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('promoted_count')->default(0);
            $table->unsignedInteger('graduated_count')->default(0);
            $table->unsignedInteger('normalized_year_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'grade', 'from_school_year']);
        });

        Schema::create('student_promotion_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('student_promotion_batches')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('old_classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->foreignId('new_classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->string('old_school_year', 9)->nullable();
            $table->string('new_school_year', 9)->nullable();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->string('result', 20);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'student_id']);
        });

        DB::table('students')
            ->select(['id', 'school_id', 'classroom_id', 'school_year', 'created_at', 'updated_at'])
            ->whereNotNull('school_year')
            ->orderBy('id')
            ->chunkById(500, function ($students): void {
                foreach ($students as $student) {
                    DB::table('student_enrollments')->insertOrIgnore([
                        'student_id' => $student->id,
                        'school_id' => $student->school_id,
                        'classroom_id' => $student->classroom_id,
                        'school_year' => $student->school_year,
                        'status' => 'current',
                        'started_at' => null,
                        'ended_at' => null,
                        'created_at' => $student->created_at,
                        'updated_at' => $student->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_promotion_items');
        Schema::dropIfExists('student_promotion_batches');
        Schema::dropIfExists('student_enrollments');

        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropUnique('classrooms_grade_letter_unique');
        });
    }
};
