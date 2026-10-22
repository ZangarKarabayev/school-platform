<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->softDeletes();
            $table->dropUnique(['student_id', 'order_date']);
        });
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unsignedTinyInteger('active_order_key')->nullable()->virtualAs('IF(deleted_at IS NULL, 1, NULL)');
                $table->unique(['student_id', 'order_date', 'active_order_key'], 'orders_student_date_active_unique');
            });
        } else {
            DB::statement('CREATE UNIQUE INDEX orders_student_date_active_unique ON orders (student_id, order_date) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        if (DB::table('orders')->select('student_id', 'order_date')->groupBy('student_id', 'order_date')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot roll back: archived and active orders share a student/date. Reconcile history before rollback.');
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_student_date_active_unique');
        });
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('active_order_key'));
        }
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->unique(['student_id', 'order_date']);
        });
    }
};
