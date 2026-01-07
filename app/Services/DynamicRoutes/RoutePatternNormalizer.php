<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes;

/**
 * Утилита для нормализации и сравнения паттернов маршрутов.
 *
 * Преобразует URI с параметрами в нормализованные паттерны и проверяет
 * конфликтность маршрутов на основе их паттернов, а не точного совпадения строк.
 *
 * @package App\Services\DynamicRoutes
 */
class RoutePatternNormalizer
{
    /**
     * Нормализовать URI паттерн.
     *
     * Преобразует все параметры маршрута в унифицированный формат {param}.
     * Это позволяет сравнивать паттерны независимо от имен параметров.
     *
     * Примеры:
     * - /products/{id} → /products/{param}
     * - /{slug} → /{param}
     * - /pages/{parent}/{slug} → /pages/{param}/{param}
     * - /products/{id}/reviews → /products/{param}/reviews
     *
     * @param string $uri URI маршрута с параметрами
     * @return string Нормализованный паттерн
     */
    public function normalize(string $uri): string
    {
        // Убираем ведущий слэш для единообразия
        $normalized = ltrim($uri, '/');

        // Заменяем все параметры маршрута на {param}
        // Паттерн: {любые_символы_кроме_} внутри фигурных скобок
        $pattern = preg_replace('/\{[^}]+\}/', '{param}', $normalized);

        // Возвращаем с ведущим слэшем для консистентности
        return '/' . ltrim($pattern, '/');
    }

    /**
     * Проверить, конфликтуют ли два паттерна маршрутов.
     *
     * Два паттерна конфликтуют, если они могут совпасть для одного и того же запроса.
     * Это происходит когда:
     * - Паттерны идентичны после нормализации
     * - Оба паттерна имеют одинаковую структуру сегментов
     *
     * Примеры конфликтов:
     * - /{slug} и /{id} → true (оба могут обработать /product1)
     * - /products/{id} и /products/{slug} → true (оба могут обработать /products/123)
     * - /products/{id} и /pages/{id} → false (разные префиксы)
     * - /{parent}/{slug} и /{category}/{id} → true (одинаковая структура)
     *
     * @param string $pattern1 Первый паттерн
     * @param string $pattern2 Второй паттерн
     * @return bool true если паттерны конфликтуют, false иначе
     */
    public function patternsConflict(string $pattern1, string $pattern2): bool
    {
        $normalized1 = $this->normalize($pattern1);
        $normalized2 = $this->normalize($pattern2);

        // Если паттерны идентичны после нормализации - они конфликтуют
        if ($normalized1 === $normalized2) {
            return true;
        }

        // Дополнительная проверка: сравниваем структуру сегментов
        // Это нужно для случаев, когда порядок параметров может отличаться
        // (хотя в Laravel это редкость, но на всякий случай)
        $segments1 = $this->extractSegments($normalized1);
        $segments2 = $this->extractSegments($normalized2);

        // Если количество сегментов разное - не конфликтуют
        if (count($segments1) !== count($segments2)) {
            return false;
        }

        // Сравниваем каждый сегмент
        // Сегменты должны совпадать: либо оба статические, либо оба параметры
        for ($i = 0; $i < count($segments1); $i++) {
            $seg1 = $segments1[$i];
            $seg2 = $segments2[$i];

            // Если один сегмент - параметр, а другой - статический, не конфликтуют
            $isParam1 = $this->isParameter($seg1);
            $isParam2 = $this->isParameter($seg2);

            if ($isParam1 !== $isParam2) {
                return false;
            }

            // Если оба статические, они должны совпадать
            if (!$isParam1 && $seg1 !== $seg2) {
                return false;
            }
        }

        // Если все сегменты совпадают по структуре - конфликтуют
        return true;
    }

    /**
     * Извлечь сегменты из URI паттерна.
     *
     * Разбивает URI на отдельные сегменты, разделенные слэшами.
     * Параметры маршрута нормализуются до {param}.
     *
     * @param string $uri URI паттерн
     * @return array<string> Массив сегментов с нормализованными параметрами
     */
    public function extractSegments(string $uri): array
    {
        // Сначала нормализуем URI, чтобы параметры стали {param}
        $normalizedUri = $this->normalize($uri);
        $normalized = ltrim($normalizedUri, '/');
        
        if (empty($normalized)) {
            return [];
        }

        return explode('/', $normalized);
    }

    /**
     * Проверить, является ли сегмент параметром маршрута.
     *
     * @param string $segment Сегмент URI
     * @return bool true если сегмент является параметром (начинается с {), false иначе
     */
    private function isParameter(string $segment): bool
    {
        return str_starts_with($segment, '{') && str_ends_with($segment, '}');
    }
}

