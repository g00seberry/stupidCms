<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Admin\AdminJsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для ответа на синхронизацию плагинов.
 *
 * Возвращает статистику синхронизации (добавлено, обновлено, удалено)
 * со статусом 202 (Accepted).
 *
 * @package App\Http\Resources
 */
class PluginSyncResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param array<string, mixed> $summary Статистика синхронизации (added, updated, removed, providers)
     */
    public function __construct(private readonly array $summary)
    {
        parent::__construct(null);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив со статусом и статистикой
     */
    public function toArray($request): array
    {
        return [
            'status' => 'accepted',
            'summary' => [
                'added' => $this->summary['added'] ?? [],
                'updated' => $this->summary['updated'] ?? [],
                'removed' => $this->summary['removed'] ?? [],
                'providers' => $this->summary['providers'] ?? [],
            ],
        ];
    }

    /**
     * Настроить HTTP ответ для PluginSync.
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


