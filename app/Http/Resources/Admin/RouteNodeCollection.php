<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

/**
 * API Resource Collection для списка RouteNode в админ-панели.
 *
 * Форматирует коллекцию узлов маршрутов с поддержкой пагинации.
 *
 * @package App\Http\Resources\Admin
 */
class RouteNodeCollection extends AdminResourceCollection
{
    /**
     * Класс ресурса для элементов коллекции.
     *
     * @var class-string<\App\Http\Resources\Admin\RouteNodeResource>
     */
    public $collects = RouteNodeResource::class;

    /**
     * Преобразовать коллекцию ресурсов в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с ключом 'data' и пагинацией
     */
    public function toArray($request): array
    {
        return array_merge(
            [
                'data' => $this->collection,
            ],
            $this->buildPagination([])
        );
    }
}

