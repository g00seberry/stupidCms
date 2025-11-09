<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class EntryTermsResource extends AdminJsonResource
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private readonly array $payload)
    {
        parent::__construct(null);
    }

    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->payload;
    }
}


