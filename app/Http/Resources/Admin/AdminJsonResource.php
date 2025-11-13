<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\Admin\Concerns\ConfiguresAdminResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Базовый класс для JSON ресурсов админ-панели.
 *
 * Автоматически применяет стандартные заголовки для админских ответов
 * через trait ConfiguresAdminResponse.
 *
 * @package App\Http\Resources\Admin
 */
abstract class AdminJsonResource extends JsonResource
{
    use ConfiguresAdminResponse;

    /**
     * Настроить HTTP ответ перед отправкой.
     *
     * Вызывает prepareAdminResponse для настройки заголовков и статуса.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    public function withResponse($request, $response): void
    {
        $this->prepareAdminResponse($request, $response);
    }

    /**
     * Точка расширения для потомков.
     *
     * Позволяет переопределить логику подготовки ответа
     * (установка статуса, cookies и т.д.).
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $this->addAdminResponseHeaders($response);
    }
}


