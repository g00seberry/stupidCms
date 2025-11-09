<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Support\JwtCookies;
use Symfony\Component\HttpFoundation\Response;

final class TokenRefreshResource extends AdminJsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    public function __construct(
        private readonly string $accessToken,
        private readonly string $refreshToken
    ) {
        parent::__construct(null);
    }

    /**
     * @return array<string, string>
     */
    public function toArray($request): array
    {
        return [
            'message' => 'Tokens refreshed successfully.',
        ];
    }

    protected function prepareAdminResponse($request, Response $response): void
    {
        $response->headers->setCookie(JwtCookies::access($this->accessToken));
        $response->headers->setCookie(JwtCookies::refresh($this->refreshToken));

        parent::prepareAdminResponse($request, $response);
    }
}
