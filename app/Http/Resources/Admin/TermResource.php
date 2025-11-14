<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для Term в админ-панели.
 *
 * Форматирует терм для ответа API, включая информацию об иерархии
 * (parent_id, children) для иерархических таксономий.
 *
 * @package App\Http\Resources\Admin
 */
class TermResource extends AdminJsonResource
{
    /**
     * @var bool Флаг создания нового терма
     */
    private bool $created;

    /**
     * @param mixed $resource Модель Term
     * @param bool $created Флаг создания нового терма
     */
    public function __construct($resource, bool $created = false)
    {
        parent::__construct($resource);
        $this->created = $created;
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * Включает информацию об иерархии (parent_id, children) для
     * иерархических таксономий, если связи загружены.
     * Поле taxonomy содержит ID таксономии.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями терма
     */
    public function toArray($request): array
    {
        $data = [
            'id' => $this->id,
            'taxonomy' => $this->taxonomy_id,
            'name' => $this->name,
            'meta_json' => $this->transformJson($this->meta_json),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];

        // Добавляем информацию об иерархии, если таксономия поддерживает её
        if ($this->taxonomy?->hierarchical) {
            $data['parent_id'] = $this->parent_id;
            
            // Если загружены дети, добавляем их
            if ($this->relationLoaded('children')) {
                $data['children'] = TermResource::collection($this->children);
            }
        }

        return $data;
    }

    /**
     * Настроить HTTP ответ для Term.
     *
     * Устанавливает статус 201 (Created) для только что созданных термов.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        if ($this->created) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        parent::prepareAdminResponse($request, $response);
    }

    /**
     * Рекурсивно преобразовать JSON данные, чтобы пустые массивы стали объектами.
     *
     * @param mixed $value Значение для преобразования
     * @return mixed Преобразованное значение
     */
    private function transformJson(mixed $value): mixed
    {
        if ($value === null || $value === []) {
            return new \stdClass();
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->transformJson($item), $value);
        }

        $object = new \stdClass();
        foreach ($value as $key => $nested) {
            $object->{$key} = $this->transformJson($nested);
        }

        return $object;
    }
}


