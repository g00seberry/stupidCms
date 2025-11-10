<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Admin\AdminJsonResource;
use Symfony\Component\HttpFoundation\Response;

class PluginSyncResource extends AdminJsonResource
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

    protected function prepareAdminResponse($request, Response $response): void
    {
        $response->setStatusCode(Response::HTTP_ACCEPTED);
        parent::prepareAdminResponse($request, $response);
    }
}


