<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Concerns;

use App\Support\Http\AdminResponseHeaders;
use Symfony\Component\HttpFoundation\Response;

trait ConfiguresAdminResponse
{
    /**
     * Устанавливает стандартные заголовки для админских ответов.
     */
    protected function addAdminResponseHeaders(Response $response): void
    {
        AdminResponseHeaders::apply($response);
    }
}


