<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class LogoutResource extends AdminJsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param array<int, Cookie> $cookies
     */
    public function __construct(
        private readonly array $cookies
    ) {
        parent::__construct(null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [];
    }

    protected function prepareAdminResponse($request, Response $response): void
    {
        foreach ($this->cookies as $cookie) {
            $response->headers->setCookie($cookie);
        }

        $response->setStatusCode(Response::HTTP_NO_CONTENT);
        $response->setContent(null);

        parent::prepareAdminResponse($request, $response);
    }
}


