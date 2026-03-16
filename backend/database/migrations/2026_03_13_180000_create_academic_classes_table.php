<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('grade');
            $table->string('letter', 2);
            $table->string('full_name', 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};