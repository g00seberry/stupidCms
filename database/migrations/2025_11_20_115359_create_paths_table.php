<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Создание таблицы paths для хранения структуры blueprint'ов.
 *
 * Таблица хранит пути (paths) внутри blueprint'ов, включая информацию
 * о типах данных, кардинальности, индексации и валидации.
 *
 * Индексы оптимизированы для запросов в MaterializationService и PathConflictValidator.
 */
return new class extends Migration {
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::create('paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_blueprint_id')->nullable()
                ->constrained('blueprints')->restrictOnDelete();
            $table->unsignedBigInteger('blueprint_embed_id')->nullable();
            $table->foreignId('parent_id')->nullable()
                ->constrained('paths')->cascadeOnDelete();

            $table->string('name');
            $table->string('full_path', 2048);
            $table->enum('data_type', ['string', 'text', 'int', 'float', 'bool', 'datetime', 'json', 'ref', 'media']);
            $table->enum('cardinality', ['one', 'many'])->default('one');
            $table->boolean('is_indexed')->default(false);
            $table->boolean('is_readonly')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('validation_rules')->nullable();
            $table->timestamps();

            $table->index('blueprint_id');
            $table->index('source_blueprint_id');
            $table->index(['blueprint_id', 'parent_id', 'sort_order'], 'idx_paths_blueprint_parent');
        });

        // Индекс для загрузки собственных paths (->whereNull('source_blueprint_id'))
        Schema::table('paths', function (Blueprint $table): void {
            $table->index(
                ['blueprint_id', 'source_blueprint_id'],
                'idx_paths_own_paths'
            );
        });

        // Уникальный индекс с префиксом для full_path (из-за лимита MySQL на длину ключа)
        // blueprint_id (8 байт) + full_path префикс (766 * 4 = 3064 байт) = 3072 байт (лимит MySQL)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                CREATE UNIQUE INDEX uq_paths_full_path_per_blueprint 
                ON paths (blueprint_id, full_path(766))
            ');
        } else {
            // Для SQLite и других СУБД используем обычный уникальный индекс
            Schema::table('paths', function (Blueprint $table) {
                $table->unique(['blueprint_id', 'full_path'], 'uq_paths_full_path_per_blueprint');
            });
        }

        // CHECK constraint для readonly инварианта (только для MySQL)
        // SQLite не поддерживает ALTER TABLE ... ADD CONSTRAINT
        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                ALTER TABLE paths ADD CONSTRAINT chk_paths_readonly_consistency CHECK (
                    (source_blueprint_id IS NULL AND blueprint_embed_id IS NULL)
                    OR (source_blueprint_id IS NOT NULL AND blueprint_embed_id IS NOT NULL AND is_readonly = 1)
                )
            ');

            // Индекс для запроса после batch insert в MaterializationService
            // Используем префикс 100 для full_path (достаточно для идентификации)
            DB::statement('
                CREATE INDEX idx_paths_materialization_lookup
                ON paths (blueprint_id, blueprint_embed_id, source_blueprint_id, full_path(100))
            ');

            // Составной индекс для оптимизации проверки конфликтов в PathConflictValidator
            // Используем префикс 766 (как в UNIQUE индексе)
            DB::statement('
                CREATE INDEX idx_paths_conflict_check
                ON paths (blueprint_id, full_path(766))
            ');
        } else {
            // Для других СУБД используем обычные индексы
            Schema::table('paths', function (Blueprint $table): void {
                $table->index(
                    ['blueprint_id', 'blueprint_embed_id', 'source_blueprint_id', 'full_path'],
                    'idx_paths_materialization_lookup'
                );
                $table->index(['blueprint_id', 'full_path'], 'idx_paths_conflict_check');
            });
        }
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        // Удаляем внешний ключ перед удалением таблицы
        // Проверяем существование FK перед удалением, так как он может быть уже удален
        // в миграции blueprint_embeds (которая откатывается раньше)
        if (Schema::hasTable('paths')) {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'paths'
                  AND CONSTRAINT_NAME = 'fk_paths_blueprint_embed'
                  AND REFERENCED_TABLE_NAME = 'blueprint_embeds'
            ");

            if (!empty($foreignKeys)) {
                Schema::table('paths', function (Blueprint $table): void {
                    $table->dropForeign('fk_paths_blueprint_embed');
                });
            }
        }

        // Удаляем индексы и констрейнты перед удалением таблицы (для явности)
        // MySQL не поддерживает DROP INDEX IF EXISTS, проверяем существование через INFORMATION_SCHEMA
        if (DB::getDriverName() === 'mysql') {
            // Удаляем CHECK-констрейнт
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'paths'
                  AND CONSTRAINT_TYPE = 'CHECK'
                  AND CONSTRAINT_NAME = 'chk_paths_readonly_consistency'
            ");

            if (!empty($constraints)) {
                DB::statement('ALTER TABLE paths DROP CHECK chk_paths_readonly_consistency');
            }

            // Удаляем уникальный индекс
            $indexes = DB::select("
                SELECT INDEX_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'paths'
                  AND INDEX_NAME = 'uq_paths_full_path_per_blueprint'
            ");

            if (!empty($indexes)) {
                DB::statement('DROP INDEX uq_paths_full_path_per_blueprint ON paths');
            }

            // Удаляем дополнительные индексы, созданные через raw SQL
            $materializationIndex = DB::select("
                SELECT INDEX_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'paths'
                  AND INDEX_NAME = 'idx_paths_materialization_lookup'
            ");

            if (!empty($materializationIndex)) {
                DB::statement('DROP INDEX idx_paths_materialization_lookup ON paths');
            }

            $conflictIndex = DB::select("
                SELECT INDEX_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'paths'
                  AND INDEX_NAME = 'idx_paths_conflict_check'
            ");

            if (!empty($conflictIndex)) {
                DB::statement('DROP INDEX idx_paths_conflict_check ON paths');
            }
        }

        Schema::dropIfExists('paths');
    }
};
