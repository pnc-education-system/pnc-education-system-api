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
        $table->string('student_id_no')->unique();
        $table->string('full_name');
        $table->enum('gender', ['Male', 'Female']);
        $table->date('dob')->nullable();
        $table->string('phone')->nullable();
        $table->string('email')->nullable();
        $table->string('province')->nullable();
        $table->string('high_school')->nullable();
        $table->foreignId('selection_batch_id')
              ->constrained()
              ->cascadeOnDelete();
        $table->enum('enrollment_status', [
            'Pending',
            'Enrolled',
            'Rejected',
            'Graduated',
            'Dropped'
        ])->default('Pending');
        $table->string('photo_path')->nullable();
        $table->year('intake_year')->nullable();
        $table->foreignId('created_by')
              ->constrained('users')
              ->cascadeOnDelete();
        $table->timestamps();
    });
}
public function down(): void
{
    Schema::dropIfExists('students');
}
};