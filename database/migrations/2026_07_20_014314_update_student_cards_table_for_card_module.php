<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_cards', function (Blueprint $table) {
            $table->date('issued_date')->nullable()->after('issued_at');
            $table->date('expired_date')->nullable()->after('issued_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_cards', function (Blueprint $table) {
            $table->dropColumn(['issued_date', 'expired_date']);
        });
    }
};
