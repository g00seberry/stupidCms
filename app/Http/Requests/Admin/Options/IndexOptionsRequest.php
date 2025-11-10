<?php

namespace App\Http\Requests\Admin\Options;

use App\Models\Option;
use App\Support\Http\ProblemResponseFactory;
use App\Support\Http\ProblemType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IndexOptionsRequest extends FormRequest
{
    private const KEY_PATTERN = '/^[a-z0-9_][a-z0-9_.-]{1,63}$/';

    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? $user->can('viewAny', Option::class) : false;
    }

    public function rules(): array
    {
        return [
            'namespace' => ['required', 'string', 'regex:' . self::KEY_PATTERN],
            'q' => ['nullable', 'string', 'max:255'],
            'deleted' => ['nullable', 'in:with,only'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'namespace' => $this->route('namespace'),
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        $code = $errors->has('namespace') ? 'INVALID_OPTION_IDENTIFIER' : 'INVALID_OPTION_FILTERS';

        $response = ProblemResponseFactory::make(
            ProblemType::VALIDATION_ERROR,
            detail: 'Invalid option filter parameters.',
            extensions: ['errors' => $errors->messages()],
            code: $code
        );

        throw new HttpResponseException($response);
    }
}

