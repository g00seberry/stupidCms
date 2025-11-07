<?php

namespace App\Routing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Генератор регулярного выражения для плоских URL с исключением зарезервированных путей.
 * 
 * Используется для создания негативного lookahead паттерна, который исключает
 * зарезервированные первые сегменты из плоской маршрутизации /{slug}.
 * 
 * При route:cache список фиксируется до следующего деплоя/инвалидации.
 * Это приемлемо, так как сами плагины/система регистрируют свои конкретные роуты
 * раньше и перехватят свои пути.
 */
final class ReservedPattern
{
    /**
     * Генерирует регулярное выражение для плоского slug с исключением зарезервированных путей.
     * 
     * Формат: негативный lookahead для зарезервированных сегментов + базовый паттерн slug.
     * 
     * Источник списка:
     * - config('stupidcms.reserved_routes.paths') (статические)
     * - reserved_routes (динамические) — берём только первый сегмент
     * 
     * @return string Regex паттерн для использования в Route::where('slug', ...)
     */
    public static function slugRegex(): string
    {
        // Получаем статические пути из конфига
        $configPaths = (array) config('stupidcms.reserved_routes.paths', []);
        $static = collect($configPaths)
            ->map(fn($p) => trim(parse_url($p, PHP_URL_PATH) ?: '/', '/'))
            ->filter()
            ->map(fn($s) => Str::before($s, '/'))
            ->filter();

        // Получаем префиксы из конфига
        $configPrefixes = (array) config('stupidcms.reserved_routes.prefixes', []);
        $prefixes = collect($configPrefixes)
            ->map(fn($p) => trim(parse_url($p, PHP_URL_PATH) ?: '/', '/'))
            ->filter()
            ->map(fn($s) => Str::before($s, '/'))
            ->filter();

        // Получаем динамические резервации из БД (только первый сегмент)
        $dynamic = collect(self::getDynamicReservedPaths())
            ->map(fn($p) => trim(parse_url($p, PHP_URL_PATH) ?: '/', '/'))
            ->filter()
            ->map(fn($s) => Str::before($s, '/'))
            ->filter();

        // Объединяем все зарезервированные первые сегменты
        $blocked = $static->merge($prefixes)
            ->merge($dynamic)
            ->unique()
            ->filter()
            ->map(fn($s) => strtolower($s)) // Normalize to lowercase
            ->map(fn($s) => preg_quote($s, '#'))
            ->values();

        // Строим негативный lookahead
        $neg = $blocked->isNotEmpty() 
            ? "(?!^(?:" . $blocked->implode('|') . ")$)" 
            : '';

        // Базовый паттерн для плоского slug: минимум 1 символ, только a-z0-9-
        // Запрет завершающего дефиса: [a-z0-9](?:[a-z0-9-]*[a-z0-9])?
        // СТРОГО lowercase, БЕЗ trailing slash — соответствует канону slug'ов (Task 21)
        // Middleware CanonicalUrl (глобальный) сделает 301 редирект на канон ДО роутинга
        // Весь паттерн должен начинаться с ^ для якоря начала строки
        return "^{$neg}[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$";
    }

    /**
     * Получает все зарезервированные пути из БД (reserved_routes).
     * 
     * @return array<string>
     */
    private static function getDynamicReservedPaths(): array
    {
        try {
            return DB::table('reserved_routes')
                ->whereIn('kind', ['path', 'prefix'])
                ->select('path')
                ->pluck('path')
                ->toArray();
        } catch (\Exception $e) {
            // Если таблицы нет (например, в тестах), возвращаем пустой массив
            return [];
        }
    }
}

