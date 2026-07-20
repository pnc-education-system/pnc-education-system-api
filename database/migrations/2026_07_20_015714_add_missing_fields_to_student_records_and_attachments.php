<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix student_records: make old columns nullable and add new columns
        Schema::table('student_records', function (Blueprint $table) {
            // Use raw SQL to modify column nullability (doctrine/dbal not installed)
            DB::statement('ALTER TABLE `student_records` MODIFY `record_type` VARCHAR(255) NULL');
            DB::statement('ALTER TABLE `student_records` MODIFY `details` TEXT NULL');

            if (!Schema::hasColumn('student_records', 'category')) {
                $table->string('category')->nullable()->after('student_id');
            }
            if (!Schema::hasColumn('student_records', 'title')) {
                $table->string('title')->nullable()->after('category');
            }
            if (!Schema::hasColumn('student_records', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (!Schema::hasColumn('student_records', 'record_date')) {
                $table->date('record_date')->nullable()->after('description');
            }
            if (!Schema::hasColumn('student_records', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('record_date');
            }
        });

        // Fix record_attachments: add missing columns
        Schema::table('record_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('record_attachments', 'file_size')) {
                $table->integer('file_size')->nullable()->after('file_type');
            }
            if (!Schema::hasColumn('record_attachments', 'uploaded_by')) {
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete()->after('file_size');
            }
        });

        // Fix evaluation_question: make score nullable, add max_score, fix FK
        Schema::table('evaluation_question', function (Blueprint $table) {
            // Make old 'score' column nullable (model uses 'max_score' instead)
            DB::statement('ALTER TABLE `evaluation_question` MODIFY `score` DECIMAL(5,2) NULL');

            if (!Schema::hasColumn('evaluation_question', 'max_score')) {
                $table->decimal('max_score', 5, 2)->nullable()->after('question_text');
            }

            // Fix FK: template_id should reference evaluation_forms, not card_templates
            $table->dropForeign(['template_id']);
            $table->foreign('template_id')->references('id')->on('evaluation_forms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            // Revert nullability
            DB::statement('ALTER TABLE `student_records` MODIFY `record_type` VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE `student_records` MODIFY `details` TEXT NOT NULL');

            $table->dropColumn(['category', 'title', 'description', 'record_date', 'created_by']);
        });

        Schema::table('record_attachments', function (Blueprint $table) {
            $table->dropColumn(['file_size', 'uploaded_by']);
        });

        Schema::table('evaluation_question', function (Blueprint $table) {
            $table->dropColumn('max_score');
        });
    }
};
