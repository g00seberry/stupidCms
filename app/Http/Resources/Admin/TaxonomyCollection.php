<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

/**
 * API Resource Collection для списка Taxonomy в админ-панели.
 *
 * Форматирует коллекцию таксономий с поддержкой пагинации.
 *
 * @package App\Http\Resources\Admin
 */
class TaxonomyCollection extends AdminResourceCollection
{
    /**
     * Класс ресурса для элементов коллекции.
     *
     * @var class-string<\App\Http\Resources\Admin\TaxonomyResource>
     */
    public $collects = TaxonomyResource::class;

    /**
     * Преобразовать коллекцию ресурсов в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с ключом 'data'
     */
    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}


