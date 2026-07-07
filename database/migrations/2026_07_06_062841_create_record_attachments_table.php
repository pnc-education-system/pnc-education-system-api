<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       Schema::create('record_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_record_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_type');
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('record_attachments');
    }
};
