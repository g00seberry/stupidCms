<?php

namespace App\Rules;

use App\Models\Term;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueTermSlug implements ValidationRule
{
    public function __construct(
        private readonly ?int $taxonomyId,
        private readonly ?int $exceptTermId = null
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->taxonomyId || ! is_string($value) || $value === '') {
            return;
        }

        $query = Term::query()
            ->where('taxonomy_id', $this->taxonomyId)
            ->where('slug', $value)
            ->whereNull('deleted_at');

        if ($this->exceptTermId) {
            $query->where('id', '!=', $this->exceptTermId);
        }

        if ($query->exists()) {
            $fail('The slug is already taken for this taxonomy.');
        }
    }
}


