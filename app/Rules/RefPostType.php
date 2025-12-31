<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Entry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: проверка соответствия post_type_id целевого Entry
 * списку допустимых post_type_id из constraints ref-поля.
 *
 * Поддерживает как одиночные значения (cardinality=one), так и массивы (cardinality=many).
 *
 * @package App\Rules
 */
final class RefPostType implements ValidationRule
{
    /**
     * @param array<int> $allowedPostTypeIds Список допустимых post_type_id
     * @param string $pathFullPath Полный путь к ref-полю (для сообщений об ошибках)
     */
    public function __construct(
        private readonly array $allowedPostTypeIds,
        private readonly string $pathFullPath
    ) {}

    /**
     * Выполнить правило валидации.
     *
     * Проверяет, что target_entry_id (или массив target_entry_id) указывает на Entry,
     * у которых post_type_id входит в список допустимых.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации (int для cardinality=one, array<int> для cardinality=many)
     * @param \Closure(string, string): void $fail Callback для добавления ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Если значение null, пропускаем валидацию (required/nullable обработают это)
        if ($value === null) {
            return;
        }

        // Собираем все entryId для валидации
        $entryIds = [];
        $entryIdToAttribute = []; // Маппинг entryId -> attribute для ошибок

        // Обрабатываем одиночное значение (cardinality=one)
        if (is_int($value) || is_numeric($value)) {
            $entryId = (int) $value;
            $entryIds[] = $entryId;
            $entryIdToAttribute[$entryId] = $attribute;
        }
        // Обрабатываем массив значений (cardinality=many)
        elseif (is_array($value)) {
            foreach ($value as $index => $entryId) {
                if (!is_int($entryId) && !is_numeric($entryId)) {
                    continue; // Пропускаем невалидные значения (другие правила обработают это)
                }

                $entryId = (int) $entryId;
                $entryIds[] = $entryId;
                $entryIdToAttribute[$entryId] = "{$attribute}.{$index}";
            }
        } else {
            // Неподдерживаемый тип значения - пропускаем (другие правила обработают это)
            return;
        }

        // Если нет entryId для проверки, выходим
        if (empty($entryIds)) {
            return;
        }

        // Загружаем все Entry одним запросом для оптимизации (избегаем N+1)
        $entries = Entry::whereIn('id', array_unique($entryIds))
            ->get()
            ->keyBy('id');

        // Валидируем каждый Entry
        foreach ($entryIds as $entryId) {
            $entry = $entries->get($entryId);

            // Если Entry не найден, пропускаем валидацию (exists правило обработает это)
            if ($entry === null) {
                continue;
            }

            // Проверяем, что post_type_id входит в список допустимых
            if (!in_array($entry->post_type_id, $this->allowedPostTypeIds, true)) {
                $allowedTypes = implode(', ', $this->allowedPostTypeIds);
                $fail("The {$entryIdToAttribute[$entryId]} must reference an entry with post_type_id in: {$allowedTypes}.");
            }
        }
    }
}

