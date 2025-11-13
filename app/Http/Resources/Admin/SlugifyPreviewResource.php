<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

/**
 * API Resource для предпросмотра slug'а в админ-панели.
 *
 * Возвращает базовый и уникальный slug для предпросмотра перед созданием записи.
 *
 * @package App\Http\Resources\Admin
 */
class SlugifyPreviewResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param string $base Базовый slug (без проверки уникальности)
     * @param string $unique Уникальный slug (с проверкой уникальности)
     */
    public function __construct(
        private readonly string $base,
        private readonly string $unique
    ) {
        parent::__construct(null);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, string> Массив с base и unique slug'ами
     */
    public function toArray($request): array
    {
        return [
            'base' => $this->base,
            'unique' => $this->unique,
        ];
    }
}


