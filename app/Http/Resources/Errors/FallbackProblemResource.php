<?php

declare(strict_types=1);

namespace App\Http\Resources\Errors;

use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

final class FallbackProblemResource extends JsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    public function __construct(private readonly string $path)
    {
        parent::__construct(null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'type' => 'about:blank',
            'title' => 'Not Found',
            'status' => Response::HTTP_NOT_FOUND,
            'detail' => 'The requested resource was not found.',
            'path' => $this->path,
        ];
    }

    public function withResponse($request, $response): void
    {
        $response->setStatusCode(Response::HTTP_NOT_FOUND);
        $response->header('Content-Type', 'application/problem+json');
        $response->header('Cache-Control', 'no-cache, private');
    }
}
