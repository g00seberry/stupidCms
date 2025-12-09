<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Создание таблицы doc_values с упрощённой структурой.
 *
 * Таблица индексированных скалярных значений из Entry.data_json.
 * Использует составной первичный ключ (entry_id, path_id, array_index).
 * date-тип сохраняется в value_datetime с временем 00:00:00.
 *
 * CHECK-констрейнт обеспечивает, что ровно одно value_* поле заполнено.
 */
return new class extends Migration {
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::create('doc_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('path_id')->constrained('paths')->cascadeOnDelete();
            $table->integer('array_index')->nullable();

            // Скалярные значения (по типу поля)
            $table->string('value_string', 500)->nullable();
            $table->bigInteger('value_int')->nullable();
            $table->double('value_float')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->dateTime('value_datetime')->nullable(); // Используется для date и datetime типов
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();

            // Уникальный индекс для (entry_id, path_id, array_index)
            // Примечание: array_index может быть NULL, поэтому используем уникальный индекс вместо первичного ключа
            $table->unique(['entry_id', 'path_id', 'array_index'], 'uq_doc_values_entry_path_idx');

            // Индексы для быстрых запросов
            $table->index('path_id');
            $table->index('value_string');
            $table->index('value_int');
            $table->index('value_float');
            $table->index('value_bool');
            $table->index('value_datetime');
        });

        // Добавить CHECK-констрейнт (только для MySQL)
        // Констрейнт: ровно одно value_* поле заполнено
        if (DB::getDriverName() === 'mysql') {
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
     * Откатить миграцию.
     */
    public function down(): void
    {
        // Удаляем CHECK-констрейнт перед удалением таблицы (только для MySQL)
        if (DB::getDriverName() === 'mysql') {
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

        Schema::dropIfExists('doc_values');
    }
};
