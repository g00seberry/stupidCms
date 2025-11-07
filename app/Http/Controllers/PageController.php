<?php

namespace App\Http\Controllers;

use App\Domain\View\TemplateResolver;
use App\Models\Entry;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Контроллер для отображения публичных страниц по плоскому URL /{slug}.
 * 
 * Обрабатывает только опубликованные страницы типа 'page'.
 * Зарезервированные пути исключаются на уровне роутинга через ReservedPattern.
 */
class PageController extends Controller
{
    public function __construct(
        private TemplateResolver $templateResolver,
    ) {}

    /**
     * Отображает опубликованную страницу по slug.
     * 
     * @param string $slug Плоский slug страницы (без слешей)
     * @return Response|View
     */
    public function show(string $slug): Response|View
    {
        // Ищем опубликованную страницу по slug
        // Используем скоупы для читабельности и единообразия со спецификацией
        // firstOrFail() автоматически выбрасывает ModelNotFoundException (404)
        $entry = Entry::published()
            ->ofType('page')
            ->where('slug', $slug)
            ->with('postType')
            ->firstOrFail();

        // Используем сервис для выбора шаблона по приоритету
        $template = $this->templateResolver->forEntry($entry);
        
        return view($template, [
            'entry' => $entry,
        ]);
    }
}

