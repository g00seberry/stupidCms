<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Http\AdminResponseHeaders;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * API Resource Collection для списка Plugin в админ-панели.
 *
 * Форматирует коллекцию плагинов с применением стандартных заголовков.
 *
 * @package App\Http\Resources
 */
class PluginCollection extends ResourceCollection
{
    /**
     * Класс ресурса для элементов коллекции.
     *
     * @var class-string<\App\Http\Resources\PluginResource>
     */
    public $collects = PluginResource::class;

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
     * Настроить HTTP ответ для PluginCollection.
     *
     * Применяет стандартные заголовки админ-панели.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    public function withResponse($request, $response): void
    {
        AdminResponseHeaders::apply($response);
    }
}


