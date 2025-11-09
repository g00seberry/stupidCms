<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Concerns;

use Symfony\Component\HttpFoundation\Response;

trait ConfiguresAdminResponse
{
    /**
     * Устанавливает стандартные заголовки для админских ответов.
     */
    protected function addAdminResponseHeaders(Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Vary', 'Cookie');
    }
}


