<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_blueprint_id')->nullable()
                ->constrained('blueprints')->restrictOnDelete();
            $table->unsignedBigInteger('blueprint_embed_id')->nullable(); // FK добавим позже
            $table->foreignId('parent_id')->nullable()
                ->constrained('paths')->cascadeOnDelete();

            $table->string('name');
            $table->string('full_path', 2048);
            $table->enum('data_type', ['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref']);
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
        }
    }

    public function down(): void
    {
        // Удаляем индекс перед удалением таблицы (для явности)
        // MySQL не поддерживает DROP INDEX IF EXISTS, проверяем существование через INFORMATION_SCHEMA
        if (DB::getDriverName() === 'mysql') {
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
        }
        Schema::dropIfExists('paths');
    }
};
