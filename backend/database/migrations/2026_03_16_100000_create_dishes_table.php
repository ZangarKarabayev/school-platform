<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dishes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category', 100);
            $table->unsignedInteger('calories')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('menu_items')->updateOrInsert(
            ['key' => 'dishes'],
            ['enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('menu_items')->where('key', 'dishes')->delete();
        Schema::dropIfExists('dishes');
    }
};
