<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('phone', 20)->nullable()->index();
            $table->string('bin', 12)->nullable()->index();
            $table->string('certificate_serial')->nullable();
            $table->string('certificate_thumbprint')->nullable()->unique();
            $table->text('subject_dn')->nullable();
            $table->text('issuer_dn')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_identities');
    }
};
