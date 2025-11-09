<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

class PluginSyncResource extends JsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param array<string, mixed> $summary
     */
    public function __construct(private readonly array $summary)
    {
        parent::__construct(null);
    }

    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'status' => 'accepted',
            'summary' => [
                'added' => $this->summary['added'] ?? [],
                'updated' => $this->summary['updated'] ?? [],
                'removed' => $this->summary['removed'] ?? [],
                'providers' => $this->summary['providers'] ?? [],
            ],
        ];
    }

    public function withResponse($request, $response): void
    {
        $response->setStatusCode(Response::HTTP_ACCEPTED);
        $response->header('Cache-Control', 'no-store, private');
        $response->header('Vary', 'Cookie');
    }
}


