<?php

declare(strict_types=1);

namespace App\Domain\View;

use App\Models\Entry;

/**
 * Интерфейс резолвера шаблонов для Entry.
 *
 * Определяет контракт для выбора Blade-шаблона для рендеринга записей.
 *
 * @package App\Domain\View
 */
interface TemplateResolver
{
    /**
     * Возвращает имя blade-шаблона для рендера Entry.
     *
     * @param \App\Models\Entry $entry Запись для рендеринга
     * @return string Имя Blade-шаблона
     */
    public function forEntry(Entry $entry): string;
}

