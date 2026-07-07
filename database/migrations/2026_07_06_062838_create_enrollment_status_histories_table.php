<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->string('old_status');
            $table->string('new_status');
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('enrollment_status_histories');
    }
};
