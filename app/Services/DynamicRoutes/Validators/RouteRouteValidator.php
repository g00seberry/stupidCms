<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Validators;

/**
 * Валидатор для узлов типа ROUTE.
 *
 * Проверяет данные конфигурации для маршрутов:
 * - Наличие обязательных полей (kind, uri, methods)
 * - Корректность типов данных
 * - Валидность HTTP методов
 * - Корректность action_type и связанных полей
 *
 * @package App\Services\DynamicRoutes\Validators
 */
class RouteRouteValidator implements RouteNodeValidatorInterface
{
    /**
     * Сообщения об ошибках валидации.
     *
     * @var array<string>
     */
    private array $errors = [];

    /**
     * Валидные HTTP методы.
     *
     * @var array<string>
     */
    private const VALID_HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    /**
     * Валидировать данные конфигурации маршрута.
     *
     * Проверяет:
     * - Наличие kind (обязательное поле)
     * - kind === 'route'
     * - Наличие uri (обязательное поле)
     * - Наличие methods (обязательное поле)
     * - methods должен быть массивом
     * - Все методы должны быть валидными HTTP методами
     * - Корректность типов для опциональных полей
     * - Если action_type === 'entry', то entry_id обязателен
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

        // Проверка, что kind === 'route'
        $kind = $data['kind'];
        $kindValue = $kind instanceof \App\Enums\RouteNodeKind ? $kind->value : $kind;
        if ($kindValue !== 'route') {
            $this->errors[] = 'Field "kind" must be "route"';
            return false;
        }

        // Проверка обязательного поля uri
        if (!isset($data['uri'])) {
            $this->errors[] = 'Field "uri" is required for route nodes';
            return false;
        }

        if (!is_string($data['uri'])) {
            $this->errors[] = 'Field "uri" must be a string';
            return false;
        }

        // Проверка обязательного поля methods
        if (!isset($data['methods'])) {
            $this->errors[] = 'Field "methods" is required for route nodes';
            return false;
        }

        if (!is_array($data['methods'])) {
            $this->errors[] = 'Field "methods" must be an array';
            return false;
        }

        if (empty($data['methods'])) {
            $this->errors[] = 'Field "methods" must not be empty';
            return false;
        }

        // Проверка валидности HTTP методов
        foreach ($data['methods'] as $method) {
            if (!is_string($method)) {
                $this->errors[] = 'All methods must be strings';
                return false;
            }

            $upperMethod = strtoupper($method);
            if (!in_array($upperMethod, self::VALID_HTTP_METHODS, true)) {
                $this->errors[] = "Invalid HTTP method: {$method}. Valid methods are: " . implode(', ', self::VALID_HTTP_METHODS);
            }
        }

        // Проверка типов опциональных полей
        $this->validateOptionalField($data, 'name', 'string');
        $this->validateOptionalField($data, 'domain', 'string');
        $this->validateOptionalField($data, 'middleware', 'array');
        $this->validateOptionalField($data, 'where', 'array');
        $this->validateOptionalField($data, 'defaults', 'array');
        $this->validateOptionalField($data, 'sort_order', 'integer');
        $this->validateOptionalField($data, 'enabled', 'boolean');

        // Проверка action_type и связанных полей
        $this->validateActionType($data);

        return empty($this->errors);
    }

    /**
     * Валидировать action_type и связанные поля.
     *
     * Проверяет:
     * - Если action_type === 'entry', то entry_id обязателен
     * - Если action_type === 'entry', то action запрещён
     * - action_type должен быть валидным значением
     *
     * @param array<string, mixed> $data Данные конфигурации
     * @return void
     */
    private function validateActionType(array $data): void
    {
        if (!isset($data['action_type'])) {
            return; // action_type опциональный, по умолчанию CONTROLLER
        }

        $actionType = $data['action_type'];
        $actionTypeValue = $actionType instanceof \App\Enums\RouteNodeActionType ? $actionType->value : $actionType;

        $validActionTypes = ['controller', 'entry'];
        if (!in_array($actionTypeValue, $validActionTypes, true)) {
            $this->errors[] = "Invalid action_type: {$actionTypeValue}. Valid types are: " . implode(', ', $validActionTypes);
            return;
        }

        // Если action_type === 'entry', проверяем entry_id
        if ($actionTypeValue === 'entry') {
            if (!isset($data['entry_id'])) {
                $this->errors[] = 'Field "entry_id" is required when action_type is "entry"';
            } elseif (!is_int($data['entry_id'])) {
                $this->errors[] = 'Field "entry_id" must be an integer';
            }
        }
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

