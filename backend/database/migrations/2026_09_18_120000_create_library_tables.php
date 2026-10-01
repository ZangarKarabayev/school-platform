<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_books', function (Blueprint $table): void {
            $table->id();
            $table->string('barcode', 64)->unique();
            $table->string('title');
            $table->text('author')->nullable();
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->enum('literature_type', ['educational', 'fiction']);
            $table->string('subject')->nullable();
            $table->unsignedTinyInteger('grade')->nullable();
            $table->string('language', 10)->nullable();
            $table->string('part', 50)->nullable();
            $table->timestamps();

            $table->index(['literature_type', 'grade']);
        });

        Schema::create('library_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('book_id')->constrained('library_books')->restrictOnDelete();
            $table->unsignedInteger('total_quantity')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'book_id']);
        });

        Schema::create('library_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_id')->constrained('library_stocks')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('issued_at');
            $table->date('due_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'issued_at']);
            $table->index(['stock_id', 'issued_at']);
            $table->index('due_at');
        });

        Schema::create('library_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained('library_loans')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('returned_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_returns');
        Schema::dropIfExists('library_loans');
        Schema::dropIfExists('library_stocks');
        Schema::dropIfExists('library_books');
    }
};
