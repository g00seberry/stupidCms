<?php

namespace App\Domain\Pages\Validation;

use App\Support\ReservedRoutes\ReservedRouteRegistry;
use Illuminate\Contracts\Validation\Rule;

class NotReservedRoute implements Rule
{
    public function __construct(
        private ReservedRouteRegistry $registry
    ) {}

    public function passes($attribute, $value): bool
    {
        $slug = self::normalizeSlug((string)$value);
        return !$this->registry->isReservedSlug($slug);
    }

    public function message(): string
    {
        return __('validation.slug_reserved', [], 'ru');
    }

    /**
     * Нормализация slug: trim, NFC, lowercase
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

