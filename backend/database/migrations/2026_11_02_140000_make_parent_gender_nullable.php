<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parents', function (Blueprint $table): void {
            $table->string('gender', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep nullable values to avoid losing parent records on rollback.
    }
};
