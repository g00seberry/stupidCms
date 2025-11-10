<?php

declare(strict_types=1);

namespace App\Http\Resources\Errors;

use App\Support\Http\AdminResponseHeaders;
use App\Support\Http\ProblemType;
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
        $payload = [
            'type' => ProblemType::NOT_FOUND->value,
            'title' => 'Not Found',
            'status' => ProblemType::NOT_FOUND->status(),
            'detail' => ProblemType::NOT_FOUND->defaultDetail(),
            'path' => $this->path,
        ];

        if ($code = ProblemType::NOT_FOUND->defaultCode()) {
            $payload['code'] = $code;
        }

        return $payload;
    }

    public function withResponse($request, $response): void
    {
        $response->setStatusCode(ProblemType::NOT_FOUND->status());
        $response->header('Content-Type', 'application/problem+json');
        AdminResponseHeaders::apply($response);
    }
}
