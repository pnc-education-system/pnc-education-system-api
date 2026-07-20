<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->foreignId('selection_batch_id')
                ->nullable()
                ->after('file_name')
                ->constrained('selection_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropForeign(['selection_batch_id']);
            $table->dropColumn('selection_batch_id');
        });
    }
};
