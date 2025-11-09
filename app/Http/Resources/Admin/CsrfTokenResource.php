<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Support\JwtCookies;
use Symfony\Component\HttpFoundation\Response;

class CsrfTokenResource extends AdminJsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    public function __construct(private readonly string $token)
    {
        parent::__construct(null);
    }

    /**
     * @return array{csrf: string}
     */
    public function toArray($request): array
    {
        return [
            'csrf' => $this->token,
        ];
    }

    protected function prepareAdminResponse($request, Response $response): void
    {
        $response->headers->setCookie(JwtCookies::csrf($this->token));

        parent::prepareAdminResponse($request, $response);
    }
}


