<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->timestamp('photo_updated_at')->nullable()->after('photo');
            $table->timestamp('photo_synced_at')->nullable()->after('photo_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['photo_updated_at', 'photo_synced_at']);
        });
    }
};
