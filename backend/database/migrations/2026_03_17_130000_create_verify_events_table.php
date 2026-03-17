<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verify_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->integer('verify_status')->nullable();
            $table->timestamp('create_time')->nullable();
            $table->string('bin');
            $table->string('unique_qr');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verify_events');
    }
};
