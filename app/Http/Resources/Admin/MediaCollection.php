<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\MediaResource;

/**
 * API Resource Collection для списка Media в админ-панели.
 *
 * Форматирует коллекцию медиа-файлов с поддержкой пагинации.
 *
 * @package App\Http\Resources\Admin
 */
class MediaCollection extends AdminResourceCollection
{
    /**
     * Класс ресурса для элементов коллекции.
     *
     * @var class-string<\App\Http\Resources\MediaResource>
     */
    public $collects = MediaResource::class;

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


