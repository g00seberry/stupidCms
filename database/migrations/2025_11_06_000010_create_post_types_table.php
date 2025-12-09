<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Создание таблицы post_types.
 *
 * Хранит типы записей (например, 'article', 'page', 'post').
 * Каждый тип может быть связан с blueprint, определяющим структуру Entry.
 */
return new class extends Migration {
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::create('post_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('template')->nullable();
            $table->json('options_json')->nullable();
            $table->unsignedBigInteger('blueprint_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        // Удаляем внешний ключ перед удалением таблицы
        // Проверяем существование FK перед удалением, так как он может быть уже удален
        // в миграции blueprints (которая откатывается позже)
        if (Schema::hasTable('post_types')) {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'post_types'
                  AND CONSTRAINT_NAME = 'post_types_blueprint_id_foreign'
                  AND REFERENCED_TABLE_NAME = 'blueprints'
            ");

            if (!empty($foreignKeys)) {
                Schema::table('post_types', function (Blueprint $table): void {
                    $table->dropForeign(['blueprint_id']);
                });
            }
        }

        Schema::dropIfExists('post_types');
    }
};
