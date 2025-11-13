<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для Template в админ-панели.
 *
 * Форматирует информацию о Blade шаблоне для ответа API,
 * включая содержимое файла при запросе.
 *
 * @package App\Http\Resources\Admin
 */
class TemplateResource extends AdminJsonResource
{
    /**
     * @var bool Флаг создания нового шаблона
     */
    private bool $created;

    /**
     * @param array<string, mixed> $resource Данные шаблона (name, path, exists, content, created_at, updated_at)
     * @param bool $created Флаг создания нового шаблона
     */
    public function __construct($resource, bool $created = false)
    {
        parent::__construct($resource);
        $this->created = $created;
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * Включает содержимое файла, если оно присутствует в ресурсе.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями шаблона
     */
    public function toArray($request): array
    {
        $data = [
            'name' => $this->resource['name'],
            'path' => $this->resource['path'],
            'exists' => $this->resource['exists'],
            'created_at' => $this->resource['created_at'] ?? null,
            'updated_at' => $this->resource['updated_at'] ?? null,
        ];

        // Добавляем содержимое, если оно присутствует
        if (isset($this->resource['content'])) {
            $data['content'] = $this->resource['content'];
        }

        return $data;
    }

    /**
     * Настроить HTTP ответ для Template.
     *
     * Устанавливает статус 201 (Created) для только что созданных шаблонов.
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

