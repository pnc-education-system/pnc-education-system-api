<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_categories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('evaluation_form_id')
            ->constrained()
            ->cascadeOnDelete();
        $table->string('name');
        $table->integer('sort_order')->default(1);
        $table->timestamps();
    });
    }
    public function down(): void
    {
        Schema::dropIfExists('evaluation_categories');
    }
};
