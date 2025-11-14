<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Миграция для исправления taxonomies в options_json типов записей.
 *
 * Преобразует taxonomies из объекта {} в массив [] для всех PostType записей.
 */
return new class extends Migration
{
    /**
     * Запустить миграцию.
     *
     * Исправляет все записи PostType, где options_json->taxonomies является объектом.
     *
     * @return void
     */
    public function up(): void
    {
        $postTypes = DB::table('post_types')->get();

        foreach ($postTypes as $postType) {
            $optionsJson = json_decode($postType->options_json, true);

            if ($optionsJson === null) {
                continue;
            }

            $needsUpdate = false;

            // Проверяем, является ли taxonomies объектом
            if (isset($optionsJson['taxonomies'])) {
                $taxonomies = $optionsJson['taxonomies'];

                // Если это объект (ассоциативный массив или stdClass), преобразуем в массив
                if (is_object($taxonomies) || (is_array($taxonomies) && ! array_is_list($taxonomies))) {
                    $optionsJson['taxonomies'] = [];
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                DB::table('post_types')
                    ->where('id', $postType->id)
                    ->update([
                        'options_json' => json_encode($optionsJson, JSON_UNESCAPED_UNICODE),
                    ]);
            }
        }
    }

    /**
     * Откатить миграцию.
     *
     * Откат не требуется, так как это исправление данных.
     *
     * @return void
     */
    public function down(): void
    {
        // Откат не требуется
    }
};
