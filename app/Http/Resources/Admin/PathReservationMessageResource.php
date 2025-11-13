<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для сообщений о резервации пути в админ-панели.
 *
 * Возвращает простое сообщение с настраиваемым HTTP статусом.
 *
 * @package App\Http\Resources\Admin
 */
class PathReservationMessageResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param string $message Текст сообщения
     * @param int $status HTTP статус ответа
     */
    public function __construct(
        private readonly string $message,
        private readonly int $status
    ) {
        parent::__construct(null);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, string> Массив с сообщением
     */
    public function toArray($request): array
    {
        return [
            'message' => $this->message,
        ];
    }

    /**
     * Настроить HTTP ответ для PathReservationMessage.
     *
     * Устанавливает указанный HTTP статус.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $response->setStatusCode($this->status);

        parent::prepareAdminResponse($request, $response);
    }
}


