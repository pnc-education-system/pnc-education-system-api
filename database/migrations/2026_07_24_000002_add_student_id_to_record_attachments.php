<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('record_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('record_attachments', 'student_id')) {
                $table->foreignId('student_id')
                      ->nullable()
                      ->after('student_record_id')
                      ->constrained()
                      ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('record_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('record_attachments', 'student_id')) {
                $table->dropForeign(['student_id']);
                $table->dropColumn('student_id');
            }
        });
    }
};
