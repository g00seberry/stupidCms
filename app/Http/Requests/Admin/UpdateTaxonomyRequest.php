<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Taxonomy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для обновления таксономии (Taxonomy).
 *
 * Валидирует данные для обновления таксономии:
 * - Все поля опциональны (sometimes)
 *
 * @package App\Http\Requests\Admin
 */
class UpdateTaxonomyRequest extends FormRequest
{
    /**
     * @var \App\Models\Taxonomy|null Кэшированная модель таксономии
     */
    private ?Taxonomy $resolvedTaxonomy = null;

    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Авторизация обрабатывается middleware маршрута.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует (все поля опциональны):
     * - label: название (максимум 255 символов)
     * - hierarchical: boolean
     * - options_json: объект
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $taxonomy = $this->taxonomy();
        $taxonomyId = $taxonomy?->getKey();

        return [
            'label' => 'sometimes|required|string|max:255',
            'hierarchical' => 'sometimes|boolean',
            'options_json' => 'sometimes|array',
        ];
    }

    /**
     * Получить таксономию из route параметра.
     *
     * @return \App\Models\Taxonomy|null Модель таксономии или null
     */
    public function taxonomy(): ?Taxonomy
    {
        if ($this->resolvedTaxonomy !== null) {
            return $this->resolvedTaxonomy;
        }

        $id = (int) $this->route('id');
        $this->resolvedTaxonomy = Taxonomy::query()->find($id);

        return $this->resolvedTaxonomy;
    }
}

