<?php

namespace App\Support\Slug;

interface UniqueSlugService
{
    /**
     * @param callable $isTaken function(string $slug): bool  — проверка занятости (в т.ч. reserved routes)
     * @param int $startFrom индекс с которого начинаем суффикс (обычно 2)
     */
    public function ensureUnique(string $slug, callable $isTaken, int $startFrom = 2): string;
}

