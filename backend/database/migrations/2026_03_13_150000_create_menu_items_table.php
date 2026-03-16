<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        DB::table('menu_items')->insert([
            ['key' => 'dashboard', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'students', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'classes', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'kitchen', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'orders', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'library', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'reports', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'devices', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'support', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
