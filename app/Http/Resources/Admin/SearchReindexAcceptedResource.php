<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для ответа на запрос реиндексации поиска.
 *
 * Возвращает информацию о запущенной job реиндексации
 * со статусом 202 (Accepted).
 *
 * @package App\Http\Resources\Admin
 */
class SearchReindexAcceptedResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param string $jobId ID job реиндексации
     * @param int $batchSize Размер батча для индексации
     * @param int $estimatedTotal Оценочное общее количество документов
     */
    public function __construct(
        private readonly string $jobId,
        private readonly int $batchSize,
        private readonly int $estimatedTotal
    ) {
        parent::__construct(null);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с информацией о job
     */
    public function toArray($request): array
    {
        return [
            'job_id' => $this->jobId,
            'batch_size' => $this->batchSize,
            'estimated_total' => $this->estimatedTotal,
        ];
    }

    /**
     * Настроить HTTP ответ для SearchReindexAccepted.
     *
     * Устанавливает статус 202 (Accepted).
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $response->setStatusCode(Response::HTTP_ACCEPTED);

        parent::prepareAdminResponse($request, $response);
    }
}


