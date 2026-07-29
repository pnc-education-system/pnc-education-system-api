<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->integer('total_rows');
            $table->integer('success_count');
            $table->integer('error_count');
            $table->enum('status', ['Pending', 'Processing', 'Completed', 'Failed']);
            $table->foreignId('imported_by')->constrained('users');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
