<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\Admin\Concerns\ConfiguresAdminResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Базовый класс для коллекций ресурсов админ-панели.
 *
 * Автоматически применяет стандартные заголовки и формирует структуру
 * пагинации для коллекций с пагинацией.
 *
 * @package App\Http\Resources\Admin
 */
abstract class AdminResourceCollection extends ResourceCollection
{
    use ConfiguresAdminResponse;

    /**
     * Настроить HTTP ответ перед отправкой.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    public function withResponse($request, $response): void
    {
        $this->prepareAdminResponse($request, $response);
    }

    /**
     * Точка расширения для потомков.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $this->addAdminResponseHeaders($response);
    }

    /**
     * Формирует стандартную структуру пагинации.
     *
     * Объединяет links и meta из пагинатора с переданными значениями.
     *
     * @param array<string, mixed> $default Значения по умолчанию
     * @param array<string, mixed>|null $meta Дополнительные метаданные (если null, генерируются автоматически)
     * @return array<string, mixed> Структура пагинации с links и meta
     */
    protected function buildPagination(array $default, ?array $meta = null): array
    {
        if (! $this->resource instanceof AbstractPaginator) {
            return $default;
        }

        $paginator = $this->resource;
        $meta ??= $this->paginationMeta($paginator);

        return [
            'links' => array_merge($default['links'] ?? [], $this->paginatorLinks($paginator)),
            'meta' => array_merge($default['meta'] ?? [], $meta),
        ];
    }

    /**
     * Извлечь метаданные пагинации из пагинатора.
     *
     * @param \Illuminate\Pagination\AbstractPaginator $paginator Пагинатор
     * @return array<string, int|null|string> Метаданные пагинации
     */
    protected function paginationMeta(AbstractPaginator $paginator): array
    {
        $firstItem = $paginator->firstItem();
        $lastItem = $paginator->lastItem();

        return [
            'current_page' => (int) $paginator->currentPage(),
            'from' => $firstItem !== null ? (int) $firstItem : null,
            'last_page' => (int) $paginator->lastPage(),
            'path' => $paginator->path(),
            'per_page' => (int) $paginator->perPage(),
            'to' => $lastItem !== null ? (int) $lastItem : null,
            'total' => (int) $paginator->total(),
        ];
    }

    /**
     * Извлечь ссылки пагинации из пагинатора.
     *
     * @param \Illuminate\Pagination\AbstractPaginator $paginator Пагинатор
     * @return array<string, string|null> Ссылки пагинации (first, last, prev, next)
     */
    private function paginatorLinks(AbstractPaginator $paginator): array
    {
        return [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }
}


