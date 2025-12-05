<?php

declare(strict_types=1);

namespace App\Domain\View;

use App\Models\Entry;
use Illuminate\Support\Facades\View;

/**
 * Резолвер для выбора Blade-шаблона на основе полей template.
 *
 * Приоритет выбора шаблона:
 * 1. Entry.template_override (если задано — используется как полное имя вью)
 * 2. PostType.template (если задано)
 * 3. templates.index (дефолтный шаблон)
 *
 * Все шаблоны должны находиться в папке templates или дочерних папках.
 */
final class BladeTemplateResolver implements TemplateResolver
{
    /**
     * @param \App\Domain\View\TemplatePathValidator $validator Валидатор путей шаблонов
     * @param string $default Дефолтный шаблон
     */
    public function __construct(
        private readonly TemplatePathValidator $validator,
        private string $default = 'templates.index',
    ) {}

    /**
     * Возвращает имя blade-шаблона для рендера Entry.
     *
     * Приоритет выбора шаблона:
     * 1. Entry.template_override (если задано — используется как полное имя вью с валидацией)
     * 2. PostType.template (если задано — используется с валидацией)
     * 3. templates.index (дефолтный шаблон)
     *
     * @param \App\Models\Entry $entry Запись для рендеринга
     * @return string Имя Blade-шаблона
     * @throws \InvalidArgumentException Если template указан, но не валиден или не найден
     */
    public function forEntry(Entry $entry): string
    {
        // 1) Entry.template_override (если задано — используем как полное имя вью)
        if (!empty($entry->template_override)) {
            $override = $entry->template_override;
            
            // Нормализуем путь и добавляем префикс, если нужно
            // ensurePrefix гарантирует, что путь начинается с templates.
            $normalized = $this->validator->ensurePrefix($override);
            
            if (!View::exists($normalized)) {
                throw new \InvalidArgumentException(
                    "Template override '{$override}' не найден. Убедитесь, что шаблон существует в папке templates."
                );
            }
            
            return $normalized;
        }

        // 2) PostType.template (если задано)
        $postType = $this->getPostType($entry);
        if ($postType !== null && !empty($postType->template)) {
            $template = $postType->template;
            
            // Нормализуем путь и добавляем префикс, если нужно
            // ensurePrefix гарантирует, что путь начинается с templates.
            $normalized = $this->validator->ensurePrefix($template);
            
            if (!View::exists($normalized)) {
                throw new \InvalidArgumentException(
                    "Template '{$template}' для PostType не найден. Убедитесь, что шаблон существует в папке templates."
                );
            }
            
            return $normalized;
        }

        // 3) Дефолтный шаблон
        return $this->default;
    }

    /**
     * Получает PostType из Entry.
     *
     * Использует загруженную связь, если доступна, иначе загружает связь.
     *
     * @param \App\Models\Entry $entry Запись
     * @return \App\Models\PostType|null Тип записи или null
     */
    private function getPostType(Entry $entry): ?\App\Models\PostType
    {
        if (!$entry->relationLoaded('postType')) {
            $entry->loadMissing('postType');
        }

        return $entry->postType;
    }
}

