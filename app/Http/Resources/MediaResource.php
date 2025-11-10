<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Admin\AdminJsonResource;
use App\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class MediaResource extends AdminJsonResource
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $previewUrls = $this->previewUrls();

        return [
            'id' => $this->id,
            'kind' => $this->resource->kind(),
            'name' => $this->original_name,
            'ext' => $this->ext,
            'mime' => $this->mime,
            'size_bytes' => (int) $this->size_bytes,
            'width' => $this->width ? (int) $this->width : null,
            'height' => $this->height ? (int) $this->height : null,
            'duration_ms' => $this->duration_ms ? (int) $this->duration_ms : null,
            'title' => $this->title,
            'alt' => $this->alt,
            'collection' => $this->collection,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'preview_urls' => $previewUrls,
            'download_url' => route('admin.v1.media.download', ['media' => $this->id]),
        ];
    }

    public function withResponse($request, $response): void
    {
        if ($this->resource instanceof Media && $this->resource->wasRecentlyCreated) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        parent::prepareAdminResponse($request, $response);
    }

    /**
     * @return array<string, string>|array{}
     */
    private function previewUrls(): array
    {
        if ($this->resource->kind() !== 'image') {
            return [];
        }

        $urls = [];

        foreach (array_keys(config('media.variants', [])) as $variant) {
            $urls[$variant] = route('admin.v1.media.preview', [
                'media' => $this->id,
                'variant' => $variant,
            ]);
        }

        return $urls;
    }
}


