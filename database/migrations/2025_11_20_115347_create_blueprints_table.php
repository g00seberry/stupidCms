<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Создание таблицы blueprints.
 *
 * Хранит схемы контента (blueprint'ы), определяющие структуру Entry.
 * Используется для типизации и валидации контента.
 */
return new class extends Migration {
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::create('blueprints', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Добавляем внешний ключ из post_types после создания таблицы blueprints
        // Это необходимо, так как post_types создается раньше, чем blueprints
        if (Schema::hasTable('post_types')) {
            Schema::table('post_types', function (Blueprint $table): void {
                $table->foreign('blueprint_id', 'post_types_blueprint_id_foreign')
                    ->references('id')
                    ->on('blueprints')
                    ->restrictOnDelete();

                $table->index('blueprint_id', 'idx_post_types_blueprint');
            });
        }
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        // Удаляем внешний ключ из post_types, который ссылается на blueprints
        // Это необходимо, так как при откате эта миграция выполняется раньше, чем post_types
        // и таблица post_types еще существует, но содержит FK на blueprints
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

        Schema::dropIfExists('blueprints');
    }
};
