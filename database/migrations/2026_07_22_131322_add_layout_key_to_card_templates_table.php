<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_templates', function (Blueprint $table) {
            $table->string('layout_key')->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('card_templates', function (Blueprint $table) {
            $table->dropColumn('layout_key');
        });
    }
};
