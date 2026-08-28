<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('school_year', 9)->nullable()->after('student_id');
            $table->foreignId('classroom_id')
                ->nullable()
                ->after('school_year')
                ->constrained('classrooms')
                ->nullOnDelete();
            $table->index(['school_year', 'classroom_id']);
        });

        DB::table('orders')
            ->select(['id', 'student_id', 'order_date'])
            ->orderBy('id')
            ->chunkById(500, function ($orders): void {
                $schoolYears = $orders
                    ->map(function ($order): string {
                        $date = CarbonImmutable::parse($order->order_date);
                        $startYear = $date->month >= 9 ? $date->year : $date->year - 1;

                        return $startYear.'-'.($startYear + 1);
                    })
                    ->unique()
                    ->values();
                $enrollments = DB::table('student_enrollments')
                    ->whereIn('student_id', $orders->pluck('student_id'))
                    ->whereIn('school_year', $schoolYears)
                    ->get(['student_id', 'school_year', 'classroom_id'])
                    ->keyBy(fn ($enrollment): string => $enrollment->student_id.'|'.$enrollment->school_year);

                foreach ($orders as $order) {
                    $date = CarbonImmutable::parse($order->order_date);
                    $startYear = $date->month >= 9 ? $date->year : $date->year - 1;
                    $schoolYear = $startYear.'-'.($startYear + 1);
                    $enrollment = $enrollments->get($order->student_id.'|'.$schoolYear);

                    DB::table('orders')->where('id', $order->id)->update([
                        'school_year' => $schoolYear,
                        'classroom_id' => $enrollment?->classroom_id,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['school_year', 'classroom_id']);
            $table->dropConstrainedForeignId('classroom_id');
            $table->dropColumn('school_year');
        });
    }
};
