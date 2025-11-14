<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для Taxonomy в админ-панели.
 *
 * Форматирует таксономию для ответа API, преобразуя options_json
 * в объекты для консистентности JSON.
 *
 * @package App\Http\Resources\Admin
 */
class TaxonomyResource extends AdminJsonResource
{
    /**
     * @var bool Флаг создания новой таксономии
     */
    private bool $created;

    /**
     * @param mixed $resource Модель Taxonomy
     * @param bool $created Флаг создания новой таксономии
     */
    public function __construct($resource, bool $created = false)
    {
        parent::__construct($resource);
        $this->created = $created;
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями таксономии
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label ?? $this->name,
            'hierarchical' => (bool) $this->hierarchical,
            'options_json' => $this->transformJson($this->options_json),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Настроить HTTP ответ для Taxonomy.
     *
     * Устанавливает статус 201 (Created) для только что созданных таксономий.
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


