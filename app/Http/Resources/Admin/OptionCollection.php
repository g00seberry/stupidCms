<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;

/**
 * API Resource Collection для списка Option в админ-панели.
 *
 * Форматирует коллекцию опций с поддержкой пагинации.
 *
 * @package App\Http\Resources\Admin
 */
class OptionCollection extends AdminResourceCollection
{
    /**
     * Класс ресурса для элементов коллекции.
     *
     * @var class-string<\Illuminate\Http\Resources\Json\JsonResource>
     */
    public $collects = OptionResource::class;

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

    /**
     * Настроить информацию о пагинации.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param array<string, mixed> $paginated Пагинированные данные
     * @param array<string, mixed> $default Значения по умолчанию
     * @return array<string, mixed> Структура пагинации
     */
    public function paginationInformation($request, $paginated, $default): array
    {
        if (! $this->resource instanceof AbstractPaginator) {
            return $default;
        }

        return $this->buildPagination($default);
    }
}

