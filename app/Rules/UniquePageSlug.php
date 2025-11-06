<?php

namespace App\Domain\Pages\Validation;

use App\Models\Entry;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class UniquePageSlug implements Rule, DataAwareRule
{
    protected array $data = [];

    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function passes($attribute, $value): bool
    {
        // Нормализация slug
        $slug = $this->normalizeSlug($value);
        
        if ($slug === '') {
            return false; // Пустой slug отсекается правилом required
        }

        // Определяем ID текущей записи (для игнорирования при обновлении)
        $ignoreId = $this->data['id'] ?? $this->data['entry_id'] ?? null;

        // Проверяем существование другой Page с таким же slug
        return !$this->existsPageSlug($slug, $ignoreId);
    }

    public function message(): string
    {
        return __('validation.unique_page_slug', [], 'ru');
    }

    /**
     * Нормализация slug: нижний регистр, trim, схлопывание дефисов
     */
    private function normalizeSlug(string $value): string
    {
        $slug = mb_strtolower(trim($value), 'UTF-8');
        $slug = preg_replace('~-{2,}~', '-', $slug);
        $slug = trim($slug, ' -_');
        return $slug;
    }

    /**
     * Проверка существования Page с указанным slug
     */
    private function existsPageSlug(string $slug, ?int $ignoreId = null, bool $includeSoftDeleted = true): bool
    {
        $query = Entry::query()
            ->whereHas('postType', fn($q) => $q->where('slug', 'page'))
            ->where('slug', $slug);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        // По умолчанию включаем soft-deleted записи (не разрешаем повторное использование)
        if ($includeSoftDeleted) {
            $query->withTrashed();
        }

        return $query->exists();
    }
}

