<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;
use App\Support\JwtCookies;
use Symfony\Component\HttpFoundation\Response;

class LoginResource extends AdminJsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    public function __construct(
        private readonly User $user,
        private readonly string $accessToken,
        private readonly string $refreshToken
    ) {
        parent::__construct(null);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray($request): array
    {
        return [
            'user' => [
                'id' => (int) $this->user->id,
                'email' => $this->user->email,
                'name' => $this->user->name,
            ],
        ];
    }

    protected function prepareAdminResponse($request, Response $response): void
    {
        $response->headers->setCookie(JwtCookies::access($this->accessToken));
        $response->headers->setCookie(JwtCookies::refresh($this->refreshToken));

        parent::prepareAdminResponse($request, $response);
    }
}


