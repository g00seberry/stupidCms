<?php

declare(strict_types=1);

namespace App\Domain\Pages\Validation;

use App\Domain\Routing\ReservedRouteRegistry;
use Illuminate\Contracts\Validation\Rule;

/**
 * Правило валидации: slug не должен быть зарезервированным путём.
 *
 * Проверяет, что slug не совпадает с зарезервированными путями или префиксами.
 * Используется для валидации slug'ов страниц.
 *
 * @package App\Domain\Pages\Validation
 */
class NotReservedRoute implements Rule
{
    /**
     * @param \App\Domain\Routing\ReservedRouteRegistry $registry Реестр зарезервированных маршрутов
     */
    public function __construct(
        private ReservedRouteRegistry $registry
    ) {}

    /**
     * Determine if the validation rule passes.
     *
     * Нормализует slug и проверяет, не зарезервирован ли он.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации
     * @return bool true, если slug не зарезервирован
     */
    public function passes($attribute, $value): bool
    {
        $slug = self::normalizeSlug((string)$value);
        return !$this->registry->isReservedSlug($slug);
    }

    /**
     * Get the validation error message.
     *
     * @return string Сообщение об ошибке
     */
    public function message(): string
    {
        return __('validation.slug_reserved', [], 'ru');
    }

    /**
     * Нормализация slug: trim, NFC, lowercase.
     *
     * @param string $slug Исходный slug
     * @return string Нормализованный slug
     */
    private static function normalizeSlug(string $slug): string
    {
        $slug = trim($slug, " \t\n\r\0\x0B/\\");
        
        if (extension_loaded('intl') && class_exists(\Normalizer::class)) {
            $slug = \Normalizer::normalize($slug, \Normalizer::FORM_C) ?: $slug;
        }
        
        return mb_strtolower($slug, 'UTF-8');
    }
}

