<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Событие: изменена опция.
 *
 * Диспатчится при изменении значения опции через OptionsRepository.
 * Содержит namespace, key, новое значение и опционально старое значение.
 *
 * @package App\Events
 */
class OptionChanged
{
    /**
     * @param string $namespace Namespace опции
     * @param string $key Ключ опции
     * @param mixed $value Новое значение опции
     * @param mixed|null $oldValue Старое значение опции (если было)
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $key,
        public readonly mixed $value,
        public readonly mixed $oldValue = null,
    ) {}
}

