<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для PostType в админ-панели.
 *
 * Форматирует тип записи для ответа API, преобразуя options_json
 * в объекты для консистентности JSON.
 *
 * @package App\Http\Resources\Admin
 */
class PostTypeResource extends AdminJsonResource
{
    /**
     * @var bool Флаг создания нового типа записи
     */
    private bool $created;

    /**
     * @var array<string>|null Предупреждения (если есть)
     */
    private ?array $warnings = null;

    /**
     * @param mixed $resource Модель PostType
     * @param bool $created Флаг создания нового типа записи
     * @param array<string>|null $warnings Предупреждения
     */
    public function __construct($resource, bool $created = false, ?array $warnings = null)
    {
        parent::__construct($resource);
        $this->created = $created;
        $this->warnings = $warnings;
        
        if ($warnings !== null && !empty($warnings)) {
            $this->additional(['meta' => ['warnings' => $warnings]]);
        }
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями типа записи
     */
    public function toArray($request): array
    {
        /** @var \App\Domain\PostTypes\PostTypeOptions $options */
        $options = $this->options_json;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'template' => $this->template,
            'options_json' => $options->toApiArray(),
            'blueprint_id' => $this->blueprint_id,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }


    /**
     * Настроить HTTP ответ для PostType.
     *
     * Устанавливает статус 201 (Created) для только что созданных типов записей.
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
}

