<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'term_ids' => 'required|array',
            'term_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('terms', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}


