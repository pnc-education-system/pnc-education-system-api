<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('template_id')->constrained('card_templates');
            $table->string('card_number')->unique();
            $table->string('qr_token')->unique();
            $table->timestamp('issued_at')->nullable();
            $table->integer('printed_count')->default(0);
            $table->sting('pdf_path')->nullable();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_cards');
    }
};