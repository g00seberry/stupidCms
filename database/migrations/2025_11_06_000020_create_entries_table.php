<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Создание таблицы entries.
 *
 * Хранит записи контента различных типов (post_types).
 * Включает индексы для оптимизации запросов опубликованных записей.
 */
return new class extends Migration {
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_type_id')->constrained('post_types')->restrictOnDelete();
            $table->string('title');
            $table->enum('status', ['draft','published'])->default('draft');
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data_json');
            $table->string('template_override')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            // Составной индекс для оптимизации запросов scopePublished()
            // Используется для частых выборок опубликованных записей
            $table->index(['status', 'published_at'], 'entries_status_published_at_idx');
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
