<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\MediaResource;

/**
 * API Resource Collection для списка Media в админ-панели.
 *
 * Форматирует коллекцию медиа-файлов с поддержкой пагинации.
 * Использует фабричный метод MediaResource::make() для каждого элемента.
 *
 * @package App\Http\Resources\Admin
 */
class MediaCollection extends AdminResourceCollection
{
    /**
     * Преобразовать коллекцию ресурсов в массив.
     *
     * Использует фабричный метод MediaResource::make() для каждого элемента,
     * чтобы автоматически выбрать нужный специализированный ресурс.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с ключом 'data'
     */
    public function toArray($request): array
    {
        return [
            'data' => $this->collection->map(function ($media) {
                return MediaResource::make($media);
            }),
        ];
    }
}


