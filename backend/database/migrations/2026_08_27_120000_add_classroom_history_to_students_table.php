<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->json('classroom_history')->nullable()->after('classroom_id');
        });

        DB::table('students')
            ->select(['id', 'classroom_id'])
            ->whereNotNull('classroom_id')
            ->orderBy('id')
            ->chunkById(500, function ($students): void {
                foreach ($students as $student) {
                    DB::table('students')
                        ->where('id', $student->id)
                        ->update([
                            'classroom_history' => json_encode([(int) $student->classroom_id], JSON_THROW_ON_ERROR),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn('classroom_history');
        });
    }
};
