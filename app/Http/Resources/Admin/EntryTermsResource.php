<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

/**
 * API Resource для термов записи в админ-панели.
 *
 * Форматирует термы, привязанные к записи, с группировкой по таксономиям.
 *
 * @package App\Http\Resources\Admin
 */
class EntryTermsResource extends AdminJsonResource
{
    /**
     * @param array<string, mixed> $payload Payload с термами (entry_id, terms, terms_by_taxonomy)
     */
    public function __construct(private readonly array $payload)
    {
        parent::__construct(null);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Payload с термами записи
     */
    public function toArray($request): array
    {
        return $this->payload;
    }
}


