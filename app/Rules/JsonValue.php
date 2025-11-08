<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;

final class JsonValue implements ValidationRule
{
    public function __construct(
        private readonly int $maxBytes = 65536,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $fail(__('validation.invalid_json_value'));
            return;
        }

        if ($this->maxBytes > 0 && strlen($encoded) > $this->maxBytes) {
            $fail(__('validation.json_value_too_large', ['max' => $this->maxBytes]));
        }
    }
}

