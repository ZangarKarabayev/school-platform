<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verify_events', function (Blueprint $table) {
            $table->string('direction', 10)->nullable();
            $table->index(['bin', 'create_time']);
        });
    }

    public function down(): void
    {
        Schema::table('verify_events', function (Blueprint $table) {
            $table->dropIndex(['bin', 'create_time']);
            $table->dropColumn('direction');
        });
    }
};
