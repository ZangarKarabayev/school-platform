<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('dish_id')->nullable()->constrained('dishes')->nullOnDelete();
            $table->date('order_date');
            $table->time('order_time')->nullable();
            $table->string('status', 20)->default('created');
            $table->boolean('transaction_status')->nullable();
            $table->text('transaction_error')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
