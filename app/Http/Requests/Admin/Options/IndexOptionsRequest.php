<?php

namespace App\Http\Requests\Admin\Options;

use App\Models\Option;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\HttpErrorException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

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
        $code = $errors->has('namespace') ? ErrorCode::INVALID_OPTION_IDENTIFIER : ErrorCode::INVALID_OPTION_FILTERS;

        /** @var ErrorFactory $factory */
        $factory = app(ErrorFactory::class);

        $detail = $code === ErrorCode::INVALID_OPTION_IDENTIFIER
            ? 'The provided option namespace/key is invalid.'
            : 'Invalid option filter parameters.';

        $payload = $factory->for($code)
            ->detail($detail)
            ->meta(['errors' => $errors->messages()])
            ->build();

        throw new HttpErrorException($payload);
    }
}

