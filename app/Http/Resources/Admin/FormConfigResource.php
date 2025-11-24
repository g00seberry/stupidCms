<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

/**
 * API Resource для FormConfig в админ-панели.
 *
 * Форматирует конфигурацию формы для ответа API.
 * Для метода show возвращает только config_json (объект с конфигурацией компонентов).
 * Для метода indexByPostType возвращает полную информацию с post_type_slug и blueprint_id.
 *
 * @package App\Http\Resources\Admin
 */
class FormConfigResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Возвращает полную информацию о конфигурации с post_type_slug и blueprint_id
     * (используется для метода indexByPostType).
     * Если config_json равен null, возвращается пустой объект {}.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями конфигурации
     */
    public function toArray($request): array
    {
        return [
            'post_type_slug' => $this->post_type_slug,
            'blueprint_id' => $this->blueprint_id,
            'config_json' => $this->config_json ?? new \stdClass(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
