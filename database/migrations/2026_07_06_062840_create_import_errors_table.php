<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_log_id')->constrained('import_logs');
            $table->integer('row_number');
            $table->string('field');
            $table->text('error_message');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('import_errors');
    }
};
