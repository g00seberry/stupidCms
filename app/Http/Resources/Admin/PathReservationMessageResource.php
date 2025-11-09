<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

class PathReservationMessageResource extends JsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    public function __construct(
        private readonly string $message,
        private readonly int $status
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
            'message' => $this->message,
        ];
    }

    public function withResponse($request, $response): void
    {
        $response->setStatusCode($this->status);
        $response->header('Cache-Control', 'no-store, private');
        $response->header('Vary', 'Cookie');
    }
}


