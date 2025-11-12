<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Symfony\Component\HttpFoundation\Response;

class TemplateResource extends AdminJsonResource
{
    private bool $created;

    public function __construct($resource, bool $created = false)
    {
        parent::__construct($resource);
        $this->created = $created;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
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

    protected function prepareAdminResponse($request, Response $response): void
    {
        if ($this->created) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        parent::prepareAdminResponse($request, $response);
    }
}

