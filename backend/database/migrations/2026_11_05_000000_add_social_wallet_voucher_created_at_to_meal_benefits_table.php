<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_benefits', function (Blueprint $table): void {
            $table->dateTime('social_wallet_voucher_created_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meal_benefits', function (Blueprint $table): void {
            $table->dropColumn('social_wallet_voucher_created_at');
        });
    }
};
