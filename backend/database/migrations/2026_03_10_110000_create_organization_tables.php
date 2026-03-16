<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ru')->nullable();
            $table->string('name_kk')->nullable();
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ru')->nullable();
            $table->string('name_kk')->nullable();
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ru')->nullable();
            $table->string('name_kk')->nullable();
            $table->string('code')->unique();
            $table->string('bin', 12)->nullable()->unique();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regions');
    }
};