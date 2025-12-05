<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Path;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: проверка соответствия конфигурации формы схеме blueprint.
 *
 * Проверяет, что все пути (full_path) в конфигурации формы существуют в схеме blueprint.
 * Пути получаются напрямую из таблицы Path через blueprint_id.
 *
 * @package App\Rules
 */
class FormConfigBlueprintRule implements ValidationRule
{
    /**
     * @var int ID blueprint для проверки
     */
    private int $blueprintId;

    /**
     * @param int $blueprintId ID blueprint для проверки
     */
    public function __construct(int $blueprintId)
    {
        $this->blueprintId = $blueprintId;
    }

    /**
     * Выполнить правило валидации.
     *
     * Проверяет, что все ключи (full_path) в config_json существуют в схеме blueprint.
     * Если путь не найден, добавляет ошибку валидации.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации (config_json)
     * @param \Closure(string, string): void $fail Callback для добавления ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || array_is_list($value)) {
            return; // Базовая валидация уже выполнена в Request
        }

        // Получаем все пути blueprint из таблицы Path
        $validPaths = Path::query()
            ->where('blueprint_id', $this->blueprintId)
            ->pluck('full_path')
            ->toArray();

        // Проверяем, что все ключи (пути) в конфигурации существуют в схеме
        $invalidPaths = [];
        foreach (array_keys($value) as $path) {
            if (! in_array($path, $validPaths, true)) {
                $invalidPaths[] = $path;
            }
        }

        if (! empty($invalidPaths)) {
            $fail(sprintf(
                'The following paths do not exist in the blueprint schema: %s',
                implode(', ', $invalidPaths)
            ));
        }
    }
}
