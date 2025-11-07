<?php

namespace App\Domain\Routing;

use App\Domain\Routing\Exceptions\InvalidPathException;

final class PathNormalizer
{
    /**
     * Нормализует путь: trim, убирает query/fragment, гарантирует ведущий /, убирает trailing /, lowercase, NFC.
     *
     * @throws InvalidPathException если путь пустой или невалидный
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

