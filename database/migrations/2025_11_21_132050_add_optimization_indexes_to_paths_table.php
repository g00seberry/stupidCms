<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Оптимизация индексов для таблицы paths.
 *
 * Добавляет составные индексы для ускорения запросов в MaterializationService
 * и PathConflictValidator.
 */
return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('paths', function (Blueprint $table) {
            // Индекс для удаления старых копий (Path::where('blueprint_embed_id', $embed->id)->delete())
            if (!$this->hasIndex('paths', 'idx_paths_blueprint_embed_id')) {
                $table->index('blueprint_embed_id', 'idx_paths_blueprint_embed_id');
            }

            // Индекс для загрузки собственных paths (->whereNull('source_blueprint_id'))
            if (!$this->hasIndex('paths', 'idx_paths_own_paths')) {
                $table->index(
                    ['blueprint_id', 'source_blueprint_id'],
                    'idx_paths_own_paths'
                );
            }
        });

        // Для MySQL используем префиксные индексы через raw SQL
        if (DB::getDriverName() === 'mysql') {
            // Индекс для запроса после batch insert в MaterializationService
            // Используем префикс 100 для full_path (достаточно для идентификации)
            if (!$this->hasIndex('paths', 'idx_paths_materialization_lookup')) {
                DB::statement('
                    CREATE INDEX idx_paths_materialization_lookup
                    ON paths (blueprint_id, blueprint_embed_id, source_blueprint_id, full_path(100))
                ');
            }

            // Составной индекс для оптимизации проверки конфликтов в PathConflictValidator
            // Используем префикс 766 (как в UNIQUE индексе)
            if (!$this->hasIndex('paths', 'idx_paths_conflict_check')) {
                DB::statement('
                    CREATE INDEX idx_paths_conflict_check
                    ON paths (blueprint_id, full_path(766))
                ');
            }
        } else {
            // Для других СУБД используем обычные индексы
            Schema::table('paths', function (Blueprint $table) {
                if (!$this->hasIndex('paths', 'idx_paths_materialization_lookup')) {
                    $table->index(
                        ['blueprint_id', 'blueprint_embed_id', 'source_blueprint_id', 'full_path'],
                        'idx_paths_materialization_lookup'
                    );
                }

                if (!$this->hasIndex('paths', 'idx_paths_conflict_check')) {
                    $table->index(['blueprint_id', 'full_path'], 'idx_paths_conflict_check');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('paths', function (Blueprint $table) {
            if ($this->hasIndex('paths', 'idx_paths_materialization_lookup')) {
                $table->dropIndex('idx_paths_materialization_lookup');
            }

            if ($this->hasIndex('paths', 'idx_paths_blueprint_embed_id')) {
                $table->dropIndex('idx_paths_blueprint_embed_id');
            }

            if (DB::getDriverName() === 'mysql') {
                // MySQL не поддерживает DROP INDEX IF EXISTS, используем проверку через hasIndex
                if ($this->hasIndex('paths', 'idx_paths_conflict_check')) {
                    DB::statement('DROP INDEX idx_paths_conflict_check ON paths');
                }
            } elseif ($this->hasIndex('paths', 'idx_paths_conflict_check')) {
                $table->dropIndex('idx_paths_conflict_check');
            }

            if ($this->hasIndex('paths', 'idx_paths_own_paths')) {
                $table->dropIndex('idx_paths_own_paths');
            }
        });
    }

    /**
     * Проверить существование индекса.
     *
     * @param string $table
     * @param string $index
     * @return bool
     */
    private function hasIndex(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $result = $connection->select(
                "SELECT COUNT(*) as count FROM information_schema.statistics 
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$connection->getDatabaseName(), $table, $index]
            );
            return (int) $result[0]->count > 0;
        }

        // Для других СУБД используем Schema::hasIndex
        return Schema::hasIndex($table, $index);
    }
};
