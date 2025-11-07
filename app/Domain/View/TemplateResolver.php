<?php

namespace App\Domain\View;

use App\Models\Entry;

interface TemplateResolver
{
    /**
     * Возвращает имя blade-шаблона для рендера Entry.
     * 
     * @param Entry $entry
     * @return string
     */
    public function forEntry(Entry $entry): string;
}

