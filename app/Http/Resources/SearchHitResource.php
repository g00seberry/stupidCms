<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Search\SearchHit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource для результата поиска (SearchHit).
 *
 * Форматирует один результат поиска для публичного API,
 * включая highlight для подсветки совпадений.
 *
 * @package App\Http\Resources
 * @property \App\Domain\Search\SearchHit $resource
 */
final class SearchHitResource extends JsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями результата поиска
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


