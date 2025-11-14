<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Taxonomy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для создания терма таксономии (Term).
 *
 * Валидирует данные для создания терма:
 * - name: обязательное название (максимум 255 символов)
 * - meta_json: опциональный объект метаданных
 * - parent_id: опциональный ID родителя (для иерархических таксономий)
 *
 * @package App\Http\Requests\Admin
 */
class StoreTermRequest extends FormRequest
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
     * Валидирует:
     * - name: обязательное название (максимум 255 символов)
     * - meta_json: опциональный объект
     * - parent_id: опциональный ID родителя (должен существовать в той же таксономии)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $taxonomy = $this->taxonomy();

        return [
            'name' => 'required|string|max:255',
            'meta_json' => 'nullable|array',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('terms', 'id')->where(function ($query) use ($taxonomy) {
                    if ($taxonomy) {
                        $query->where('taxonomy_id', $taxonomy->id);
                    }
                }),
            ],
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

        $id = (int) $this->route('taxonomy');

        $this->resolvedTaxonomy = Taxonomy::query()->find($id);

        return $this->resolvedTaxonomy;
    }
}

