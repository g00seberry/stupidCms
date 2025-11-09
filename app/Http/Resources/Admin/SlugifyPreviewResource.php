<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class SlugifyPreviewResource extends AdminJsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    public function __construct(
        private readonly string $base,
        private readonly string $unique
    ) {
        parent::__construct(null);
    }

    /**
     * @param Request $request
     * @return array<string, string>
     */
    public function toArray($request): array
    {
        return [
            'base' => $this->base,
            'unique' => $this->unique,
        ];
    }
}


