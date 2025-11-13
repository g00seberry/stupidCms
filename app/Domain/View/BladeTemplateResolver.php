<?php

declare(strict_types=1);

namespace App\Domain\View;

use App\Models\Entry;
use Illuminate\Support\Facades\View;

/**
 * Резолвер для выбора Blade-шаблона по файловой конвенции.
 * 
 * Приоритет:
 * 1. Entry.template_override (если задано — используется как полное имя вью)
 * 2. entry--{postType}--{slug} (если существует)
 * 3. entry--{postType} (если существует)
 * 4. entry (глобальный)
 */
final class BladeTemplateResolver implements TemplateResolver
{
    public function __construct(
        private string $default = 'entry',
    ) {}

    /**
     * Возвращает имя blade-шаблона для рендера Entry.
     *
     * Приоритет выбора шаблона:
     * 1. Entry.template_override (если задано — используется как полное имя вью)
     * 2. entry--{postType}--{slug} (если существует)
     * 3. entry--{postType} (если существует)
     * 4. entry (глобальный шаблон по умолчанию)
     *
     * @param \App\Models\Entry $entry Запись для рендеринга
     * @return string Имя Blade-шаблона
     * @throws \InvalidArgumentException Если template_override указан, но шаблон не найден
     */
    public function forEntry(Entry $entry): string
    {
        // 1) Entry.template_override (если задано — используем как полное имя вью)
        if (!empty($entry->template_override)) {
            $override = $entry->template_override;
            if (!View::exists($override)) {
                throw new \InvalidArgumentException(
                    "Template override '{$override}' не найден. Убедитесь, что шаблон существует."
                );
            }
            return $override;
        }

        // Получаем postType slug
        $postTypeSlug = $this->getPostTypeSlug($entry);
        
        // Получаем entry slug
        $entrySlug = $entry->slug;

        // 2) Проверяем entry--{postType}--{slug}
        if ($postTypeSlug && $entrySlug) {
            $specificTemplate = "entry--{$postTypeSlug}--{$entrySlug}";
            if (View::exists($specificTemplate)) {
                return $specificTemplate;
            }
        }

        // 3) Проверяем entry--{postType}
        if ($postTypeSlug) {
            $typeTemplate = "entry--{$postTypeSlug}";
            if (View::exists($typeTemplate)) {
                return $typeTemplate;
            }
        }

        // 4) Глобальный entry
        return $this->default;
    }

    /**
     * Получает slug postType из Entry.
     *
     * Использует загруженную связь, если доступна, иначе выполняет запрос к БД.
     *
     * @param \App\Models\Entry $entry Запись
     * @return string|null Slug типа записи или null
     */
    private function getPostTypeSlug(Entry $entry): ?string
    {
        if ($entry->relationLoaded('postType') && $entry->postType) {
            return $entry->postType->slug;
        }

        return $entry->postType()->value('slug');
    }
}

