<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Search\SearchHit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SearchHit $resource
 */
final class SearchHitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hit = $this->resource;

        return [
            'id' => $hit->id,
            'post_type' => $hit->postType,
            'slug' => $hit->slug,
            'title' => $hit->title,
            'excerpt' => $hit->excerpt,
            'score' => $hit->score,
            'highlight' => $hit->highlight,
        ];
    }
}


