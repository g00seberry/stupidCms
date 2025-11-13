<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Symfony\Component\HttpFoundation\Response;

class PostTypeResource extends AdminJsonResource
{
    private bool $created;
    private ?array $warnings = null;

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
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            /** @deprecated Используйте файловую конвенцию entry--{postType} */
            'template' => $this->template,
            'options_json' => $this->transformOptionsJson($this->options_json),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Recursively normalize options_json to ensure JSON objects remain objects ({}).
     */
    private function transformOptionsJson(mixed $value): mixed
    {
        if ($value === null) {
            return new \stdClass();
        }

        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return new \stdClass();
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->transformOptionsJson($item), $value);
        }

        $object = new \stdClass();
        foreach ($value as $key => $nested) {
            $object->{$key} = $this->transformOptionsJson($nested);
        }

        return $object;
    }


    protected function prepareAdminResponse($request, Response $response): void
    {
        if ($this->created) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        parent::prepareAdminResponse($request, $response);
    }
}

