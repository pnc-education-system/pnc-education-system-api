<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            $table->string('student_code')->unique();
            $table->string('first_name');
            $table->string('last_name');

            $table->string('gender')->nullable();
            $table->date('dob')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->enum('enrollment_status', [
                'Pending',
                'Approved',
                'Rejected',
                'Graduated'
            ])->default('Pending')->index();

            $table->foreignId('batch_id')
                ->nullable()
                ->constrained('selection_batches')
                ->nullOnDelete()
                ->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};