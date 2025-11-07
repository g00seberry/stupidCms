<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Создает таблицу reserved_routes для динамического резервирования URL-путей.
     * Используется плагинами и системными модулями для защиты своих маршрутов.
     */
    public function up(): void
    {
        Schema::create('reserved_routes', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->comment('Канонический путь: /foo, lowercase, без trailing /');
            $table->enum('kind', ['prefix', 'path'])->default('path')->comment('Тип резервации: prefix для префиксов, path для точных путей');
            $table->string('source', 100)->comment('Источник резервирования (system:name, plugin:name, module:name)');
            $table->timestamps();

            $table->index('source');
        });

        // STORED-колонка для регистронезависимой уникальности и нормализации
        // Гарантирует канон: /foo (lowercase, trim trailing slash) на уровне БД
        // Это защищает от случайного ввода не-lowercase путей при прямом создании записей
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                "ALTER TABLE `reserved_routes` ADD COLUMN `path_norm` VARCHAR(255) AS (LOWER(TRIM(BOTH '/' FROM `path`))) STORED"
            );
            DB::statement(
                "ALTER TABLE `reserved_routes` ADD UNIQUE INDEX `uniq_path_norm` (`path_norm`)"
            );
        } else {
            // SQLite: используем обычный UNIQUE на path (для тестов)
            DB::statement(
                "CREATE UNIQUE INDEX `uniq_path` ON `reserved_routes` (`path`)"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reserved_routes');
    }
};
