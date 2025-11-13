<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Symfony\Component\HttpFoundation\Response;

class TaxonomyResource extends AdminJsonResource
{
    private bool $created;

    public function __construct($resource, bool $created = false)
    {
        parent::__construct($resource);
        $this->created = $created;
    }

    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label ?? $this->name,
            'hierarchical' => (bool) $this->hierarchical,
            'options_json' => $this->transformJson($this->options_json),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function prepareAdminResponse($request, Response $response): void
    {
        if ($this->created) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        parent::prepareAdminResponse($request, $response);
    }

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


