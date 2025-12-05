<?php

declare(strict_types=1);

namespace App\Domain\View;

/**
 * Валидатор путей шаблонов.
 *
 * Проверяет, что шаблон находится в папке templates или дочерних папках.
 * Все остальные директории считаются системными и недоступны для шаблонов.
 *
 * @package App\Domain\View
 */
final class TemplatePathValidator
{
    /**
     * Префикс для шаблонов.
     */
    private const TEMPLATE_PREFIX = 'templates.';

    /**
     * Проверить, что путь шаблона валиден.
     *
     * Шаблон должен начинаться с 'templates.' и не содержать опасных символов.
     *
     * @param string $template Путь к шаблону
     * @return bool true, если путь валиден
     */
    public function validate(string $template): bool
    {
        if ($template === '') {
            return false;
        }

        // Нормализуем путь
        $normalized = $this->normalize($template);

        // Проверяем, что путь начинается с templates.
        return str_starts_with($normalized, self::TEMPLATE_PREFIX);
    }

    /**
     * Нормализовать путь шаблона.
     *
     * Убирает лишние точки, слеши, нормализует разделители.
     *
     * @param string $template Исходный путь
     * @return string Нормализованный путь
     */
    public function normalize(string $template): string
    {
        // Убираем пробелы в начале и конце
        $template = trim($template);

        // Заменяем слеши на точки (Blade использует точки для разделения путей)
        $template = str_replace(['/', '\\'], '.', $template);

        // Убираем множественные точки
        $template = preg_replace('/\.{2,}/', '.', $template) ?? $template;

        // Убираем точку в начале и конце
        $template = trim($template, '.');

        return $template;
    }

    /**
     * Проверить и нормализовать путь шаблона.
     *
     * Если путь не начинается с templates., добавляет префикс.
     * Затем нормализует путь.
     *
     * @param string $template Исходный путь
     * @return string Нормализованный путь с префиксом templates.
     */
    public function ensurePrefix(string $template): string
    {
        $normalized = $this->normalize($template);

        // Если уже начинается с templates., возвращаем как есть
        if (str_starts_with($normalized, self::TEMPLATE_PREFIX)) {
            return $normalized;
        }

        // Добавляем префикс
        return self::TEMPLATE_PREFIX . $normalized;
    }
}

