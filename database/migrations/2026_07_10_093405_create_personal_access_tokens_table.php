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
        // This migration duplicates 2026_07_06_065726_create_personal_access_tokens_table.php.
        // Because both would create the same table, we intentionally do nothing here.
        return;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally no-op to avoid dropping the table created by 2026_07_06_065726.
        return;
    }
};

