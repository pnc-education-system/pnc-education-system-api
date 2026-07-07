<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('student_id')
            ->constrained()
            ->cascadeOnDelete();
        $table->foreignId('evaluation_form_id')
            ->constrained()
            ->cascadeOnDelete();
        $table->string('evaluation_period');
        $table->decimal('total_score', 6, 2)->default(0);
        $table->enum('status', [
            'Draft',
            'Submitted',
            'Reviewed',
            'Approved'
        ])->default('Draft');
        $table->timestamp('submitted_at')->nullable();
        $table->foreignId('reviewed_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();
        $table->timestamps();
    });
}
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};