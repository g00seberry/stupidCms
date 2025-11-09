<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchReindexAcceptedResource extends AdminJsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    public function __construct(
        private readonly string $jobId,
        private readonly int $batchSize,
        private readonly int $estimatedTotal
    ) {
        parent::__construct(null);
    }

    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'job_id' => $this->jobId,
            'batch_size' => $this->batchSize,
            'estimated_total' => $this->estimatedTotal,
        ];
    }

    protected function prepareAdminResponse($request, Response $response): void
    {
        $response->setStatusCode(Response::HTTP_ACCEPTED);

        parent::prepareAdminResponse($request, $response);
    }
}


