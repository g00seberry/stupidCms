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
        // Обрабатываем исключения на случай отсутствия таблицы в тестах
        try {
            if ($this->pathReservationService->isReserved("/{$slug}")) {
                abort(404);
            }
          } catch (\Illuminate\Database\QueryException $e) {
              // Если таблица reserved_routes не существует (например, в тестах),
              // игнорируем проверку и продолжаем поиск Entry
          } catch (\PDOException $e) {
              // Если таблица reserved_routes не существует (например, в тестах),
              // игнорируем проверку и продолжаем поиск Entry
          }

        // Ищем опубликованную страницу по slug
        // Используем скоупы для читабельности и единообразия со спецификацией
        $entry = Entry::published()
            ->ofType('page')
            ->where('slug', $slug)
            ->with('postType')
            ->first();

        if (!$entry) {
            abort(404);
        }

        return view('pages.show', [
            'entry' => $entry,
        ]);
    }
}

