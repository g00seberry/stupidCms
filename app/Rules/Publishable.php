<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that if is_published is true, the slug is present and valid.
 * This rule should be applied to the 'slug' field.
 */
class Publishable implements ValidationRule, DataAwareRule
{
    protected array $data = [];

    /**
     * Set the data under validation.
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isPublished = $this->data['is_published'] ?? false;

        // If not being published, no additional checks needed
        if (! $isPublished) {
            return;
        }

        if (is_string($value) && trim($value) === '') {
            $fail('A valid slug is required when publishing an entry.');
            return;
        }

        $isUpdate = isset($this->data['_method']) || request()->isMethod('PUT') || request()->isMethod('PATCH');

        if ($isUpdate && (! is_string($value) || trim($value) === '')) {
            $fail('A valid slug is required when publishing an entry.');
        }
    }
}

