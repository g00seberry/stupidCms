<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция для добавления CHECK-констрейнтов в doc_values.
 *
 * Добавляет:
 * - Денормализованное поле cardinality для проверки array_index
 * - CHECK-констрейнт: ровно одно value_* поле заполнено
 * - CHECK-констрейнт: array_index обязателен для cardinality=many, NULL для one
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('doc_values', function (Blueprint $table): void {
            // Добавить денормализованное поле cardinality
            $table->enum('cardinality', ['one', 'many'])->default('one')->after('path_id');
        });

        // Заполнить существующие данные cardinality из paths
        // Для MySQL используем JOIN, для SQLite - подзапрос
        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                UPDATE doc_values dv
                INNER JOIN paths p ON dv.path_id = p.id
                SET dv.cardinality = p.cardinality
            ');
        } else {
            // SQLite синтаксис
            DB::statement('
                UPDATE doc_values
                SET cardinality = (
                    SELECT p.cardinality
                    FROM paths p
                    WHERE p.id = doc_values.path_id
                )
            ');
        }

        // Добавить CHECK-констрейнты (только для MySQL)
        if (DB::getDriverName() === 'mysql') {
            // Констрейнт: ровно одно value_* поле заполнено
            DB::statement('
                ALTER TABLE doc_values ADD CONSTRAINT chk_doc_values_single_value CHECK (
                    (value_string IS NOT NULL) + 
                    (value_int IS NOT NULL) + 
                    (value_float IS NOT NULL) + 
                    (value_bool IS NOT NULL) + 
                    (value_date IS NOT NULL) + 
                    (value_datetime IS NOT NULL) + 
                    (value_text IS NOT NULL) + 
                    (value_json IS NOT NULL) = 1
                )
            ');

            // Констрейнт: array_index обязателен для cardinality=many, NULL для one
            DB::statement('
                ALTER TABLE doc_values ADD CONSTRAINT chk_doc_values_array_index CHECK (
                    (cardinality = \'one\' AND array_index IS NULL) OR
                    (cardinality = \'many\' AND array_index IS NOT NULL)
                )
            ');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE doc_values DROP CHECK IF EXISTS chk_doc_values_single_value');
            DB::statement('ALTER TABLE doc_values DROP CHECK IF EXISTS chk_doc_values_array_index');
        }

        Schema::table('doc_values', function (Blueprint $table): void {
            $table->dropColumn('cardinality');
        });
    }
};
