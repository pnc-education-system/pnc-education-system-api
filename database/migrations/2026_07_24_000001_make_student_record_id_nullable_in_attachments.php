<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('record_attachments', function (Blueprint $table) {
            $table->dropForeign(['student_record_id']);
            $table->unsignedBigInteger('student_record_id')->nullable()->change();
            $table->foreign('student_record_id')
                  ->references('id')
                  ->on('student_records')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('record_attachments', function (Blueprint $table) {
            $table->dropForeign(['student_record_id']);
            $table->unsignedBigInteger('student_record_id')->nullable(false)->change();
            $table->foreign('student_record_id')
                  ->references('id')
                  ->on('student_records')
                  ->cascadeOnDelete();
        });
    }
};
