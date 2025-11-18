<?php

declare(strict_types=1);

namespace App\Domain\Media\Validation;

use RuntimeException;

/**
 * Валидатор конфигурации медиа-файлов.
 *
 * Проверяет корректность конфигурации, включая обязательные варианты изображений.
 *
 * @package App\Domain\Media\Validation
 */
final class MediaConfigValidator
{
    /**
     * Обязательные варианты изображений, которые должны быть всегда настроены.
     *
     * @var array<string>
     */
    private const REQUIRED_VARIANTS = ['thumbnail', 'medium', 'large'];

    /**
     * Валидировать конфигурацию медиа-файлов.
     *
     * Проверяет наличие обязательных вариантов изображений в конфигурации.
     * Выбрасывает RuntimeException, если обязательные варианты отсутствуют.
     *
     * @return void
     * @throws \RuntimeException Если обязательные варианты отсутствуют или некорректно настроены
     */
    public function validate(): void
    {
        $variants = config('media.variants', []);

        if (! is_array($variants)) {
            throw new RuntimeException('Config key "media.variants" must be an array.');
        }

        $missing = [];

        foreach (self::REQUIRED_VARIANTS as $required) {
            if (! array_key_exists($required, $variants)) {
                $missing[] = $required;
            } elseif (! is_array($variants[$required])) {
                throw new RuntimeException("Config key \"media.variants.{$required}\" must be an array.");
            } elseif (! isset($variants[$required]['max'])) {
                throw new RuntimeException("Config key \"media.variants.{$required}.max\" is required.");
            }
        }

        if (! empty($missing)) {
            $missingList = implode(', ', $missing);
            throw new RuntimeException(
                "Required media variants are missing in config: {$missingList}. " .
                "These variants must always be configured: " . implode(', ', self::REQUIRED_VARIANTS) . '.'
            );
        }
    }
}

