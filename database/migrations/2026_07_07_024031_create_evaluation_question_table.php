<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('evaluation_forms')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('evaluation_categories')->cascadeOnDelete();
            $table->text('question_text');
            $table->decimal('score', 5, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('evaluation_question');
    }
};
