<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Term;
use App\Models\Taxonomy;
use App\Rules\NoTermCycle;
use App\Rules\UniqueTermSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для обновления терма таксономии (Term).
 *
 * Валидирует данные для обновления терма:
 * - Все поля опциональны (sometimes)
 * - Проверяет уникальность slug в рамках таксономии (исключая текущий терм)
 * - Проверяет, что parent_id не указывает на самого себя
 * - Проверяет, что parent_id не создаст циклическую зависимость
 *
 * @package App\Http\Requests\Admin
 */
class UpdateTermRequest extends FormRequest
{
    /**
     * @var \App\Models\Term|null Кэшированная модель терма
     */
    private ?Term $resolvedTerm = null;

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
 * - name: название (максимум 255 символов)
 * - slug: slug (regex, уникальность в рамках таксономии)
 * - meta_json: объект метаданных
 * - parent_id: ID родителя (должен существовать в той же таксономии, не может быть самим собой, не должен создавать цикл)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $term = $this->term();
        $taxonomy = $term?->taxonomy ?: $this->taxonomyFromRoute();

        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                new UniqueTermSlug($taxonomy?->getKey(), $term?->getKey()),
            ],
            'meta_json' => 'sometimes|nullable|array',
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('terms', 'id')->where(function ($query) use ($taxonomy, $term) {
                    if ($taxonomy) {
                        $query->where('taxonomy_id', $taxonomy->id);
                    }
                    if ($term) {
                        // Нельзя сделать родителем самого себя
                        $query->where('id', '!=', $term->id);
                    }
                }),
                new NoTermCycle($term),
            ],
        ];
    }

    /**
     * Получить терм из route параметра.
     *
     * @return \App\Models\Term|null Модель терма или null
     */
    public function term(): ?Term
    {
        if ($this->resolvedTerm !== null) {
            return $this->resolvedTerm;
        }

        $termId = (int) $this->route('term');
        $this->resolvedTerm = Term::query()
            ->with('taxonomy')
            ->find($termId);

        return $this->resolvedTerm;
    }

    /**
     * Получить таксономию из route параметра.
     *
     * @return \App\Models\Taxonomy|null Модель таксономии или null
     */
    private function taxonomyFromRoute(): ?Taxonomy
    {
        $id = (int) $this->route('taxonomy');
        if ($id === 0) {
            return null;
        }

        return Taxonomy::query()->find($id);
    }
}

