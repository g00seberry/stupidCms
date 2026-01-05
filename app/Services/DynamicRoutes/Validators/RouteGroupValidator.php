<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Validators;

/**
 * Валидатор для узлов типа GROUP.
 *
 * Проверяет данные конфигурации для групп маршрутов:
 * - Наличие обязательного поля kind
 * - Корректность типов данных для полей группы
 * - Валидность структуры children (если указано)
 *
 * @package App\Services\DynamicRoutes\Validators
 */
class RouteGroupValidator implements RouteNodeValidatorInterface
{
    /**
     * Сообщения об ошибках валидации.
     *
     * @var array<string>
     */
    private array $errors = [];

    /**
     * Валидировать данные конфигурации группы.
     *
     * Проверяет:
     * - Наличие kind (обязательное поле)
     * - kind === 'group'
     * - Корректность типов для опциональных полей (prefix, domain, namespace, middleware, where)
     * - children должен быть массивом (если указан)
     *
     * @param array<string, mixed> $data Данные конфигурации
     * @return bool true если данные валидны, false иначе
     */
    public function validate(array $data): bool
    {
        $this->errors = [];

        // Проверка обязательного поля kind
        if (!isset($data['kind'])) {
            $this->errors[] = 'Field "kind" is required';
            return false;
        }

        // Проверка, что kind === 'group'
        $kind = $data['kind'];
        $kindValue = $kind instanceof \App\Enums\RouteNodeKind ? $kind->value : $kind;
        if ($kindValue !== 'group') {
            $this->errors[] = 'Field "kind" must be "group"';
            return false;
        }

        // Проверка типов опциональных полей
        $this->validateOptionalField($data, 'prefix', 'string');
        $this->validateOptionalField($data, 'domain', 'string');
        $this->validateOptionalField($data, 'namespace', 'string');
        $this->validateOptionalField($data, 'middleware', 'array');
        $this->validateOptionalField($data, 'where', 'array');
        $this->validateOptionalField($data, 'sort_order', 'integer');
        $this->validateOptionalField($data, 'enabled', 'boolean');

        // Проверка children (если указано)
        if (isset($data['children'])) {
            if (!is_array($data['children'])) {
                $this->errors[] = 'Field "children" must be an array';
                return false;
            }
        }

        return empty($this->errors);
    }

    /**
     * Получить сообщения об ошибках валидации.
     *
     * @return array<string> Массив сообщений об ошибках
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Валидировать опциональное поле.
     *
     * Проверяет тип поля, если оно указано.
     *
     * @param array<string, mixed> $data Данные конфигурации
     * @param string $field Имя поля
     * @param string $expectedType Ожидаемый тип (string, array, integer, boolean)
     * @return void
     */
    private function validateOptionalField(array $data, string $field, string $expectedType): void
    {
        if (!isset($data[$field])) {
            return; // Поле опциональное, пропускаем
        }

        $value = $data[$field];
        $actualType = gettype($value);

        // Специальная обработка для enum
        if ($expectedType === 'string' && $value instanceof \BackedEnum) {
            return; // Enum считается валидной строкой
        }

        // Проверка типа
        if ($actualType !== $expectedType) {
            $this->errors[] = "Field \"{$field}\" must be of type {$expectedType}, got {$actualType}";
        }
    }
}

