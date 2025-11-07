<?php

namespace App\Http\Controllers;

use App\Domain\Routing\PathReservationService;
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
        private PathReservationService $pathReservationService
    ) {}

    /**
     * Отображает опубликованную страницу по slug.
     * 
     * @param string $slug Плоский slug страницы (без слешей)
     * @return Response|View
     */
    public function show(string $slug): Response|View
    {
        // Дополнительная защита: проверяем, не зарезервирован ли путь
        // (на случай, если список изменился после route:cache)
        // Обрабатываем исключения только для кейса "table not found" в тестах/миграциях
        try {
            if ($this->pathReservationService->isReserved("/{$slug}")) {
                abort(404);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Разрешаем только кейс "table not found" (42S02 для MySQL, HY000 для SQLite)
            // Остальные ошибки логируем и пробрасываем дальше
            $code = (string) $e->getCode();
            if (!in_array($code, ['42S02', 'HY000'], true)) {
                report($e);
                throw $e;
            }
            // Для SQLite также проверяем сообщение об ошибке
            if ($code === 'HY000' && !str_contains($e->getMessage(), 'no such table')) {
                report($e);
                throw $e;
            }
        } catch (\PDOException $e) {
            // Аналогично для PDOException
            $code = (string) $e->getCode();
            if (!in_array($code, ['42S02', 'HY000'], true)) {
                report($e);
                throw $e;
            }
            if ($code === 'HY000' && !str_contains($e->getMessage(), 'no such table')) {
                report($e);
                throw $e;
            }
        }

        // Ищем опубликованную страницу по slug
        // Используем скоупы для читабельности и единообразия со спецификацией
        // firstOrFail() автоматически выбрасывает ModelNotFoundException (404)
        $entry = Entry::published()
            ->ofType('page')
            ->where('slug', $slug)
            ->with('postType')
            ->firstOrFail();

        return view('pages.show', [
            'entry' => $entry,
        ]);
    }
}

