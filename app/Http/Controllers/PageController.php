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
 * Обрабатывает только опубликованные страницы типа 'page'.
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
        $entry = Entry::published()
            ->ofType('page')
            ->where('slug', $slug)
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

