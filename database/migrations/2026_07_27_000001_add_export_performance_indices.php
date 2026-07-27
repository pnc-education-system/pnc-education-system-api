<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indices for export/reporting queries.
     *
     * R5 Requirement: Indexed/chunked queries must complete ≤10s for 5,000 records.
     * These indices cover the most common filter, sort, and join patterns.
     */
    public function up(): void
    {
        // Students table — index columns commonly filtered/sorted in exports
        Schema::table('students', function (Blueprint $table) {
            // Single-column indices for filter fields
            $table->index('enrollment_status', 'students_enrollment_status_index');
            $table->index('province',          'students_province_index');
            $table->index('intake_year',       'students_intake_year_index');
            $table->index('full_name',         'students_full_name_index');

            // Composite indices for common export filter combinations
            // e.g. "all enrolled students in a batch"
            $table->index(['selection_batch_id', 'enrollment_status'], 'students_batch_status_index');
            // e.g. "all students in a given intake year with a status"
            $table->index(['intake_year', 'enrollment_status'], 'students_year_status_index');
        });

        // Import logs — index for listing/filtering import history in reports
        Schema::table('import_logs', function (Blueprint $table) {
            $table->index('status',            'import_logs_status_index');
            $table->index('imported_by',       'import_logs_imported_by_index');
            $table->index('created_at',        'import_logs_created_at_index');
        });

        // Student cards — index for card generation statistics
        Schema::table('student_cards', function (Blueprint $table) {
            $table->index('template_id',       'student_cards_template_id_index');
            $table->index('issued_at',         'student_cards_issued_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_enrollment_status_index');
            $table->dropIndex('students_province_index');
            $table->dropIndex('students_intake_year_index');
            $table->dropIndex('students_full_name_index');
            $table->dropIndex('students_batch_status_index');
            $table->dropIndex('students_year_status_index');
        });

        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropIndex('import_logs_status_index');
            $table->dropIndex('import_logs_imported_by_index');
            $table->dropIndex('import_logs_created_at_index');
        });

        Schema::table('student_cards', function (Blueprint $table) {
            $table->dropIndex('student_cards_template_id_index');
            $table->dropIndex('student_cards_issued_at_index');
        });
    }
};
