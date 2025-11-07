<?php

namespace App\Domain\View;

use App\Models\Entry;
use Illuminate\Support\Facades\View;

/**
 * Резолвер для выбора Blade-шаблона по приоритету:
 * 1. Override по slug (pages.overrides.{slug})
 * 2. По типу поста (pages.types.{postType->slug})
 * 3. Default (pages.show)
 */
final class BladeTemplateResolver implements TemplateResolver
{
    /**
     * Кэш для результатов View::exists() в рамках одного запроса.
     * 
     * @var array<string, bool>
     */
    private array $existsCache = [];

    public function __construct(
        private string $default = 'pages.show',
        private string $overridePrefix = 'pages.overrides.',
        private string $typePrefix = 'pages.types.',
    ) {}

    /**
     * Проверяет существование view с мемоизацией результата.
     * 
     * @param string $name Имя view
     * @return bool
     */
    private function viewExists(string $name): bool
    {
        return $this->existsCache[$name] ??= View::exists($name);
    }

    /**
     * Очищает недопустимые символы из slug для безопасности.
     * 
     * @param string $slug
     * @return string
     */
    private function sanitizeSlug(string $slug): string
    {
        // Оставляем только буквы, цифры, дефисы и подчеркивания
        // Defense-in-depth: даже если slug валидируется на входе, обрезаем здесь
        return (string) preg_replace('/[^a-z0-9\-_]/i', '', $slug);
    }

    /**
     * Возвращает имя blade-шаблона для рендера Entry.
     * 
     * Приоритет:
     * 1. Override по slug (если файл существует)
     * 2. По типу поста (если файл существует)
     * 3. Default
     * 
     * @param Entry $entry
     * @return string
     */
    public function forEntry(Entry $entry): string
    {
        // 1) Override по slug (с санитизацией)
        $sanitizedSlug = $this->sanitizeSlug($entry->slug);
        if ($sanitizedSlug !== '') {
            $override = $this->overridePrefix . $sanitizedSlug;
            if ($this->viewExists($override)) {
                return $override;
            }
        }

        // 2) По типу поста (берем slug из связи postType)
        if ($entry->relationLoaded('postType') && $entry->postType) {
            $typeKey = $entry->postType->slug;
        } else {
            $typeKey = $entry->postType()->value('slug') ?? 'page';
        }
        
        // Санитизация типа поста для безопасности
        $sanitizedTypeKey = $this->sanitizeSlug($typeKey);
        if ($sanitizedTypeKey !== '') {
            $typeView = $this->typePrefix . $sanitizedTypeKey;
            if ($this->viewExists($typeView)) {
                return $typeView;
            }
        }

        // 3) Дефолт
        return $this->default;
    }
}

