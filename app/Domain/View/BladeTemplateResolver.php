<?php

namespace App\Domain\View;

use App\Models\Entry;

/**
 * Резолвер для выбора Blade-шаблона по приоритету:
 * 1. Entry.template_override (если задан)
 * 2. PostType.template (если задан)
 * 3. Default (pages.show) - если оба не заданы
 */
final class BladeTemplateResolver implements TemplateResolver
{
    public function __construct(
        private string $default = 'pages.show',
    ) {}

    /**
     * Возвращает имя blade-шаблона для рендера Entry.
     * 
     * Приоритет:
     * 1. Entry.template_override (если задан)
     * 2. PostType.template (если задан)
     * 3. Default (pages.show) - если оба не заданы
     * 
     * @param Entry $entry
     * @return string
     */
    public function forEntry(Entry $entry): string
    {
        // 1) Entry.template_override
        if (!empty($entry->template_override)) {
            return $entry->template_override;
        }

        // 2) PostType.template
        if ($entry->relationLoaded('postType') && $entry->postType) {
            $template = $entry->postType->template;
        } else {
            $template = $entry->postType()->value('template');
        }
        
        if (!empty($template)) {
            return $template;
        }

        // 3) Default
        return $this->default;
    }
}

