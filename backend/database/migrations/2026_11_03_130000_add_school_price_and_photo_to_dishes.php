<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table): void {
            $table->foreignId('school_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_main')->default(false);
            $table->softDeletes();
            $table->index(['school_id', 'category']);
            $table->dropColumn('calories');
        });
    }

    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table): void {
            $table->unsignedInteger('calories')->nullable();
            $table->dropIndex(['school_id', 'category']);
            $table->dropConstrainedForeignId('school_id');
            $table->dropColumn(['price', 'photo_path', 'is_main']);
            $table->dropSoftDeletes();
        });
    }
};
