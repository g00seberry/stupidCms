<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Entry;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для Entry в админ-панели.
 *
 * Форматирует Entry для ответа API, включая связанные сущности
 * (postType, author, terms, blueprint) при их загрузке.
 *
 * @package App\Http\Resources\Admin
 */
class EntryResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Возвращает массив с полями записи, включая:
     * - Основные поля (id, post_type_id, title, status)
     * - JSON поля (content_json, meta_json) преобразованные в объекты (null для пустых значений)
     * - Связанные сущности (author, terms, blueprint) при их загрузке
     * - Даты в ISO 8601 формате
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив данных записи
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'post_type_id' => $this->post_type_id,
            'title' => $this->title,
            'status' => $this->status,
            'content_json' => $this->transformJson($this->data_json),
            'meta_json' => $this->transformJson($this->seo_json),
            'is_published' => $this->status === 'published',
            'published_at' => $this->published_at?->toIso8601String(),
            'template_override' => $this->template_override,
            'author' => $this->when($this->relationLoaded('author'), function () {
                return [
                    'id' => $this->author?->id,
                    'name' => $this->author?->name,
                ];
            }),
            'terms' => $this->when($this->relationLoaded('terms'), function () {
                return $this->terms->map(function ($term) {
                    return [
                        'id' => $term->id,
                        'name' => $term->name,
                        'taxonomy' => $term->taxonomy_id,
                    ];
                });
            }),
            'blueprint' => $this->when(
                $this->postType?->relationLoaded('blueprint') && $this->postType?->blueprint,
                function () {
                    return BlueprintResource::make($this->postType->blueprint);
                }
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * Рекурсивно преобразовать JSON данные для сериализации.
     *
     * Преобразует ассоциативные массивы в объекты stdClass для корректной
     * сериализации в JSON. Сохраняет типы данных: списки остаются массивами,
     * null и пустые массивы возвращаются как null.
     *
     * @param mixed $value Значение для преобразования
     * @return mixed Преобразованное значение (null для пустых значений)
     */
    private function transformJson(mixed $value): mixed
    {
        // null возвращается как null
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return $value;
        }

        // Пустой массив возвращается как null
        if (empty($value)) {
            return null;
        }

        // Список (массив с числовыми ключами) - сохраняем как массив
        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->transformJson($item), $value);
        }

        // Ассоциативный массив (объект) - преобразуем в stdClass
        $object = new \stdClass();
        foreach ($value as $key => $nested) {
            $object->{$key} = $this->transformJson($nested);
        }

        return $object;
    }

    /**
     * Настроить HTTP ответ для Entry.
     *
     * Устанавливает статус 201 (Created) для только что созданных записей.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        if ($this->resource instanceof Entry && $this->resource->wasRecentlyCreated) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        parent::prepareAdminResponse($request, $response);
    }
}

