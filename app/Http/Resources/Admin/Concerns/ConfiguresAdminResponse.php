<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Concerns;

use App\Support\Http\AdminResponseHeaders;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait для настройки HTTP ответов админ-панели.
 *
 * Применяет стандартные заголовки для всех админских ресурсов
 * через AdminResponseHeaders.
 *
 * @package App\Http\Resources\Admin\Concerns
 */
trait ConfiguresAdminResponse
{
    /**
     * Установить стандартные заголовки для админских ответов.
     *
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function addAdminResponseHeaders(Response $response): void
    {
        AdminResponseHeaders::apply($response);
    }
}


