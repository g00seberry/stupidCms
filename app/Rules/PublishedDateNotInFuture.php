<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Carbon;

class PublishedDateNotInFuture implements Rule, DataAwareRule
{
    protected array $data = [];

    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function passes($attribute, $value): bool
    {
        $status = $this->data['status'] ?? 'draft';
        if ($status !== 'published' || empty($value)) {
            return true;
        }

        return Carbon::parse($value, 'UTC')->lte(Carbon::now('UTC'));
    }

    public function message(): string
    {
        return __('validation.published_at_not_in_future', [], 'ru');
    }
}

