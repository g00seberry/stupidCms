<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\View\TemplateResolver;
use App\Http\Requests\PageShowRequest;
use App\Models\Entry;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Контроллер для отображения публичных страниц по плоскому URL /{slug}.
 *
 * Обрабатывает все опубликованные entries (любого типа) через entry_slugs с is_current=true.
 * Зарезервированные пути исключаются на уровне роутинга через ReservedPattern.
 */
final class PageController extends Controller
{
    public function __construct(
        private readonly TemplateResolver $templateResolver,
        private readonly ViewFactory $view,
    ) {
    }

    public function show(PageShowRequest $request, string $slug): View
    {
        // Ищем entry через entry_slugs с is_current=true (как в документации)
        // Поддерживаем все типы, не только 'page'
        $entry = Entry::published()
            ->whereHas('slugs', fn($q) => $q->where('slug', $slug)->where('is_current', true))
            ->with('postType')
            ->first();

        if ($entry === null) {
            throw new NotFoundHttpException('Page not found.');
        }

        $template = $this->templateResolver->forEntry($entry);

        return $this->view->make($template, [
            'entry' => $entry,
        ]);
    }
}

