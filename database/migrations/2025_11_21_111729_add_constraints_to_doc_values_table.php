<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция для добавления CHECK-констрейнта в doc_values.
 *
 * Добавляет CHECK-констрейнт: ровно одно value_* поле заполнено.
 * Логика проверки array_index реализована в EntryIndexer (без денормализации cardinality).
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
        // Добавить CHECK-констрейнт (только для MySQL)
        if (DB::getDriverName() === 'mysql') {
            // Констрейнт: ровно одно value_* поле заполнено
            DB::statement('
                ALTER TABLE doc_values ADD CONSTRAINT chk_doc_values_single_value CHECK (
                    (value_string IS NOT NULL) + 
                    (value_int IS NOT NULL) + 
                    (value_float IS NOT NULL) + 
                    (value_bool IS NOT NULL) + 
                    (value_datetime IS NOT NULL) + 
                    (value_text IS NOT NULL) + 
                    (value_json IS NOT NULL) = 1
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
            // MySQL не поддерживает DROP CHECK IF EXISTS, проверяем существование через INFORMATION_SCHEMA
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'doc_values'
                  AND CONSTRAINT_TYPE = 'CHECK'
                  AND CONSTRAINT_NAME = 'chk_doc_values_single_value'
            ");

            $constraintNames = array_column($constraints, 'CONSTRAINT_NAME');

            if (in_array('chk_doc_values_single_value', $constraintNames, true)) {
                DB::statement('ALTER TABLE doc_values DROP CHECK chk_doc_values_single_value');
            }
        }
    }
};
