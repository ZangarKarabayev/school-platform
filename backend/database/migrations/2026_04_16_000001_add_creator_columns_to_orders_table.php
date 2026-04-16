<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('dish_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('created_by_terminal_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('terminals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_terminal_id');
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
