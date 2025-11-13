<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Option;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для Option в админ-панели.
 *
 * Форматирует опцию для ответа API, преобразуя value_json
 * в объекты для консистентности JSON.
 *
 * @package App\Http\Resources\Admin
 */
class OptionResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями опции
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'namespace' => $this->namespace,
            'key' => $this->key,
            'value' => $this->transformJson($this->value_json),
            'description' => $this->description,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
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

    /**
     * Настроить HTTP ответ для Option.
     *
     * Устанавливает статус 201 (Created) для только что созданных опций.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        if ($this->resource instanceof Option && $this->resource->wasRecentlyCreated) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        parent::prepareAdminResponse($request, $response);
    }
}

