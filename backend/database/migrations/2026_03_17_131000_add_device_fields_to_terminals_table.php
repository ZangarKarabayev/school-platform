<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('terminals')) {
            Schema::create('terminals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->nullable();
                $table->unsignedBigInteger('device_id')->nullable();
                $table->string('ip')->nullable();
                $table->string('mac_addr')->nullable();
                $table->timestamp('time')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('terminals', function (Blueprint $table) {
            if (!Schema::hasColumn('terminals', 'school_id')) {
                $table->unsignedBigInteger('school_id')->nullable()->after('id');
            }

            if (!Schema::hasColumn('terminals', 'device_id')) {
                $table->unsignedBigInteger('device_id')->nullable()->after('school_id');
            }

            if (!Schema::hasColumn('terminals', 'ip')) {
                $table->string('ip')->nullable()->after('device_id');
            }

            if (!Schema::hasColumn('terminals', 'mac_addr')) {
                $table->string('mac_addr')->nullable()->after('ip');
            }

            if (!Schema::hasColumn('terminals', 'time')) {
                $table->timestamp('time')->nullable()->after('mac_addr');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('terminals')) {
            return;
        }

        Schema::table('terminals', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('terminals', 'school_id') ? 'school_id' : null,
                Schema::hasColumn('terminals', 'device_id') ? 'device_id' : null,
                Schema::hasColumn('terminals', 'ip') ? 'ip' : null,
                Schema::hasColumn('terminals', 'mac_addr') ? 'mac_addr' : null,
                Schema::hasColumn('terminals', 'time') ? 'time' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

