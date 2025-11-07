<?php

namespace App\Http\Controllers;

use App\Domain\View\TemplateResolver;
use App\Models\Entry;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

/**
 * Контроллер для главной страницы (/).
 * 
 * Читает опцию site:home_entry_id и рендерит:
 * - Если опция указывает на опубликованную запись → эту запись
 * - Иначе → дефолтный шаблон home.default
 */
final class HomeController
{
    public function __construct(
        private ViewFactory $view,
        private TemplateResolver $templateResolver,
    ) {}

    public function __invoke(): \Illuminate\Contracts\View\View
    {
        $id = options('site', 'home_entry_id');
        
        if ($id) {
            $entry = Entry::query()
                ->whereKey($id)
                ->where('status', 'published')
                ->where('published_at', '<=', now())
                ->with('postType')
                ->first();
                
            if ($entry) {
                // Унифицированный рендер записи через сервис выбора шаблонов
                $template = $this->templateResolver->forEntry($entry);
                return $this->view->make($template, ['entry' => $entry]);
            }
        }
        
        return $this->view->make('home.default');
    }
}

