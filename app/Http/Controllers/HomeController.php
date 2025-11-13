<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\View\TemplateResolver;
use App\Http\Requests\HomeRequest;
use App\Models\Entry;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * Контроллер для главной страницы (/).
 *
 * Читает опцию site:home_entry_id и рендерит:
 * - Если опция указывает на опубликованную запись → эту запись
 * - Иначе → дефолтный шаблон home.default
 *
 * @package App\Http\Controllers
 */
final class HomeController
{
    /**
     * @param \Illuminate\Contracts\View\Factory $view Фабрика представлений
     * @param \App\Domain\View\TemplateResolver $templateResolver Резолвер шаблонов
     */
    public function __construct(
        private readonly ViewFactory $view,
        private readonly TemplateResolver $templateResolver,
    ) {
    }

    /**
     * Обработать запрос к главной странице.
     *
     * @param \App\Http\Requests\HomeRequest $request Запрос
     * @return \Illuminate\Contracts\View\View Представление
     */
    public function __invoke(HomeRequest $request): View
    {
        $option = options('site', 'home_entry_id');
        $entryId = is_numeric($option) ? (int) $option : null;

        if ($entryId !== null) {
            $entry = Entry::query()
                ->whereKey($entryId)
                ->where('status', 'published')
                ->where('published_at', '<=', Carbon::now('UTC'))
                ->with('postType')
                ->first();

            if ($entry !== null) {
                $template = $this->templateResolver->forEntry($entry);

                return $this->view->make($template, ['entry' => $entry]);
            }
        }

        return $this->view->make('home.default');
    }
}

