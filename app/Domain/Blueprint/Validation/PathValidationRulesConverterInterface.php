<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Интерфейс конвертера правил валидации из Path в доменные Rule объекты.
 *
 * Преобразует validation_rules из модели Path в массив доменных Rule объектов.
 * Не выполняет проверок совместимости правил с типами данных или cardinality.
 *
 * @package App\Domain\Blueprint\Validation
 */
interface PathValidationRulesConverterInterface
{
    /**
     * Преобразовать validation_rules из Path в доменные Rule объекты.
     *
     * Преобразует все ключи из validation_rules в Rule объекты напрямую,
     * без проверок совместимости с типами данных или cardinality.
     *
     * @param array<string, mixed>|null $validationRules Правила валидации из Path (может быть null)
     * @param string $dataType Тип данных Path (не используется, оставлен для обратной совместимости)
     * @param string $cardinality Кардинальность (не используется, оставлен для обратной совместимости)
     * @return list<\App\Domain\Blueprint\Validation\Rules\Rule> Массив доменных Rule объектов
     * @throws \InvalidArgumentException Если встречено неизвестное правило
     */
    public function convert(
        ?array $validationRules,
        string $dataType,
        string $cardinality
    ): array;
}

