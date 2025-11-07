<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Добавляет индекс по slug для оптимизации поиска записей по slug в PageController.
     */
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->index('slug', 'entries_slug_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropIndex('entries_slug_idx');
        });
    }
};
