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
 * Обрабатывает все опубликованные entries (любого типа) по slug из entries.slug.
 * Зарезервированные пути исключаются на уровне роутинга через ReservedPattern.
 *
 * @package App\Http\Controllers
 */
final class PageController extends Controller
{
    /**
     * @param \App\Domain\View\TemplateResolver $templateResolver Резолвер шаблонов
     * @param \Illuminate\Contracts\View\Factory $view Фабрика представлений
     */
    public function __construct(
        private readonly TemplateResolver $templateResolver,
        private readonly ViewFactory $view,
    ) {
    }

    /**
     * Отобразить страницу по slug.
     *
     * Ищет entry по slug напрямую из таблицы entries.
     *
     * @param \App\Http\Requests\PageShowRequest $request Запрос
     * @param string $slug Slug страницы
     * @return \Illuminate\Contracts\View\View Представление
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException Если страница не найдена
     */
    public function show(PageShowRequest $request, string $slug): View
    {
        $entry = Entry::published()
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

