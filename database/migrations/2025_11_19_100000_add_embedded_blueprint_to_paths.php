<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('paths', function (Blueprint $table) {
            // Какой Blueprint встраиваем
            $table->foreignId('embedded_blueprint_id')
                ->nullable()
                ->after('ref_target_type')
                ->constrained('blueprints')
                ->onDelete('restrict');

            // Корневой Path для этой материализации
            $table->foreignId('embedded_root_path_id')
                ->nullable()
                ->after('embedded_blueprint_id')
                ->constrained('paths')
                ->onDelete('cascade');

            $table->index('embedded_root_path_id', 'idx_embedded_root');
        });

        // Расширяем enum data_type
        // MySQL поддерживает ALTER, SQLite требует пересоздания таблицы
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE paths MODIFY COLUMN data_type ENUM('string', 'int', 'float', 'bool', 'text', 'json', 'ref', 'blueprint') NOT NULL");
        } else {
            // Для SQLite и других БД пересоздаём таблицу
            Schema::table('paths', function (Blueprint $table) {
                $table->dropColumn('data_type');
            });
            
            Schema::table('paths', function (Blueprint $table) {
                $table->enum('data_type', [
                    'string', 'int', 'float', 'bool', 'text', 'json', 'ref', 'blueprint'
                ])->after('full_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paths', function (Blueprint $table) {
            $table->dropForeign(['embedded_blueprint_id']);
            $table->dropForeign(['embedded_root_path_id']);
            $table->dropIndex('idx_embedded_root');
            $table->dropColumn(['embedded_blueprint_id', 'embedded_root_path_id']);
        });

        // Возвращаем enum обратно
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE paths MODIFY COLUMN data_type ENUM('string', 'int', 'float', 'bool', 'text', 'json', 'ref') NOT NULL");
        } else {
            // Для SQLite и других БД пересоздаём таблицу
            Schema::table('paths', function (Blueprint $table) {
                $table->dropColumn('data_type');
            });
            
            Schema::table('paths', function (Blueprint $table) {
                $table->enum('data_type', [
                    'string', 'int', 'float', 'bool', 'text', 'json', 'ref'
                ])->after('full_path');
            });
        }
    }
};

