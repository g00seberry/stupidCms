<?php

namespace App\Support\Slug;

final class DefaultUniqueSlugService implements UniqueSlugService
{
    private const MAX_ATTEMPTS = 10000;

    public function ensureUnique(string $base, callable $isTaken, int $startFrom = 2): string
    {
        $slug = $base;
        $i = $startFrom;
        $attempts = 0;

        while ($isTaken($slug) && $attempts < self::MAX_ATTEMPTS) {
            // Если база уже заканчивается на -N, заменяем суффикс
            if (preg_match('~-\d+$~', $base)) {
                $slug = preg_replace('~-\d+$~', "-{$i}", $base);
            } else {
                $slug = "{$base}-{$i}";
            }
            $i++;
            $attempts++;
        }

        if ($attempts >= self::MAX_ATTEMPTS) {
            // Fallback: добавляем случайный суффикс
            $slug = $base . '-' . time();
        }

        return $slug;
    }
}

