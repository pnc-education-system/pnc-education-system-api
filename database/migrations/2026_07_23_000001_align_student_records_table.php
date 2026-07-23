<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            if (!Schema::hasColumn('student_records', 'category')) {
                $table->string('category')->default('general')->after('student_id');
            }

            if (!Schema::hasColumn('student_records', 'title')) {
                $table->string('title')->default('Student record')->after('category');
            }

            if (!Schema::hasColumn('student_records', 'description')) {
                $table->text('description')->nullable()->after('title');
            }

            if (!Schema::hasColumn('student_records', 'record_date')) {
                $table->date('record_date')->nullable()->after('description');
            }

            if (!Schema::hasColumn('student_records', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('record_date')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('student_records', function (Blueprint $table) {
            if (Schema::hasColumn('student_records', 'record_type')) {
                $table->dropColumn('record_type');
            }

            if (Schema::hasColumn('student_records', 'details')) {
                $table->dropColumn('details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->string('record_type')->default('general')->after('student_id');
            $table->text('details')->nullable()->after('record_type');
        });

        Schema::table('student_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['category', 'title', 'description', 'record_date']);
        });
    }
};
