<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Validators;

/**
 * Интерфейс для валидаторов узлов маршрутов.
 *
 * Определяет контракт для валидации данных конфигурации RouteNode.
 * Каждый валидатор отвечает за проверку данных для определённого типа узла.
 *
 * @package App\Services\DynamicRoutes\Validators
 */
interface RouteNodeValidatorInterface
{
    /**
     * Валидировать данные конфигурации узла.
     *
     * Проверяет наличие обязательных полей, корректность типов данных
     * и бизнес-правила для типа узла.
     *
     * @param array<string, mixed> $data Данные конфигурации
     * @return bool true если данные валидны, false иначе
     */
    public function validate(array $data): bool;

    /**
     * Получить сообщения об ошибках валидации.
     *
     * Возвращает массив сообщений об ошибках, если валидация не прошла.
     *
     * @return array<string> Массив сообщений об ошибках
     */
    public function getErrors(): array;
}

