<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use App\Domain\Routing\Exceptions\InvalidPathException;

/**
 * Сервис для нормализации путей.
 *
 * Приводит пути к единому формату: удаляет query/fragment, гарантирует ведущий слэш,
 * убирает trailing слэш, приводит к нижнему регистру, применяет Unicode NFC нормализацию.
 *
 * @package App\Domain\Routing
 */
final class PathNormalizer
{
    /**
     * Нормализует путь: trim, убирает query/fragment, гарантирует ведущий /, убирает trailing /, lowercase, NFC.
     *
     * Выполняет следующие преобразования:
     * - Удаляет query string и fragment
     * - Удаляет пробелы в начале и конце
     * - Удаляет относительные сегменты (./, ../)
     * - Удаляет дублирующие слэши
     * - Гарантирует ведущий слэш
     * - Удаляет trailing слэш (кроме корня)
     * - Приводит к нижнему регистру
     * - Применяет Unicode NFC нормализацию (если доступна)
     *
     * @param string $raw Исходный путь
     * @return string Нормализованный путь
     * @throws \App\Domain\Routing\Exceptions\InvalidPathException Если путь пустой или невалидный
     */
    public static function normalize(string $raw): string
    {
        // Убираем query и fragment
        $path = parse_url($raw, PHP_URL_PATH) ?? $raw;
        
        // Trim пробелов
        $path = trim($path);
        
        // Проверка на пустые/невалидные значения
        if ($path === '' || $path === '#' || $path === '?') {
            throw new InvalidPathException("Invalid path: '{$raw}'");
        }
        
        // Защита от относительных путей и дублирующих слэшей
        // Убираем ./ и ../ сегменты
        $path = str_replace(['./', '../'], '', $path);
        // Убираем дублирующие слэши (// → /)
        $path = preg_replace('#/+#', '/', $path);
        
        // Гарантируем ведущий /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        
        // Убираем trailing / (кроме корня)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        
        // Приводим к нижнему регистру
        $path = mb_strtolower($path, 'UTF-8');
        
        // Unicode NFC нормализация
        if (extension_loaded('intl') && class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($path, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $path = $normalized;
            }
        }
        
        return $path;
    }
}

