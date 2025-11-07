<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            // Составной индекс для оптимизации запросов scopePublished()
            // Используется для частых выборок опубликованных записей
            $table->index(['status', 'published_at'], 'entries_status_published_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropIndex('entries_status_published_at_idx');
        });
    }
};

