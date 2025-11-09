<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

class AdminPingResource extends AdminJsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param array<string, string> $payload
     */
    public function __construct(private readonly array $payload)
    {
        parent::__construct(null);
    }

    /**
     * @return array<string, string>
     */
    public function toArray($request): array
    {
        return $this->payload;
    }
}


