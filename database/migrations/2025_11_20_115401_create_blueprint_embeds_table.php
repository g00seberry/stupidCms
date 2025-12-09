<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Создание таблицы blueprint_embeds.
 *
 * Хранит информацию о встроенных blueprint'ах внутри других blueprint'ов.
 * Позволяет создавать иерархические структуры контента.
 */
return new class extends Migration {
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::create('blueprint_embeds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('embedded_blueprint_id')
                ->constrained('blueprints')->restrictOnDelete();
            $table->unsignedBigInteger('host_path_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['blueprint_id', 'embedded_blueprint_id', 'host_path_id'],
                'uq_blueprint_embed'
            );
            $table->index('embedded_blueprint_id', 'idx_embeds_embedded');
            $table->index('blueprint_id', 'idx_embeds_blueprint');
        });

        // Добавляем внешний ключ host_path_id после создания таблицы paths
        if (Schema::hasTable('paths')) {
            Schema::table('blueprint_embeds', function (Blueprint $table): void {
                $table->foreign('host_path_id', 'blueprint_embeds_host_path_id_foreign')
                    ->references('id')
                    ->on('paths')
                    ->cascadeOnDelete();
            });
        }

        // Добавляем внешний ключ из paths после создания таблицы blueprint_embeds
        // Это необходимо, так как paths создается раньше, чем blueprint_embeds
        if (Schema::hasTable('paths')) {
            Schema::table('paths', function (Blueprint $table): void {
                $table->foreign('blueprint_embed_id', 'fk_paths_blueprint_embed')
                    ->references('id')
                    ->on('blueprint_embeds')
                    ->cascadeOnDelete();

                $table->index('blueprint_embed_id', 'idx_paths_embed');
            });
        }
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        // Удаляем внешний ключ из paths, который ссылается на blueprint_embeds
        // Это необходимо, так как при откате эта миграция выполняется раньше, чем paths
        // и таблица paths еще существует, но содержит FK на blueprint_embeds
        if (Schema::hasTable('paths')) {
            Schema::table('paths', function (Blueprint $table): void {
                // Проверяем существование FK перед удалением
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'paths'
                      AND CONSTRAINT_NAME = 'fk_paths_blueprint_embed'
                      AND REFERENCED_TABLE_NAME = 'blueprint_embeds'
                ");

                if (!empty($foreignKeys)) {
                    $table->dropForeign('fk_paths_blueprint_embed');
                }
            });
        }

        // Удаляем внешний ключ host_path_id перед удалением таблицы
        // Проверяем существование FK перед удалением
        if (Schema::hasTable('blueprint_embeds')) {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'blueprint_embeds'
                  AND COLUMN_NAME = 'host_path_id'
                  AND REFERENCED_TABLE_NAME = 'paths'
            ");

            if (!empty($foreignKeys)) {
                Schema::table('blueprint_embeds', function (Blueprint $table) use ($foreignKeys): void {
                    $table->dropForeign($foreignKeys[0]->CONSTRAINT_NAME);
                });
            }
        }

        Schema::dropIfExists('blueprint_embeds');
    }
};
