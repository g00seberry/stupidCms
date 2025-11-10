<?php

namespace App\Http\Requests\Admin\Options;

use App\Models\Option;
use App\Rules\JsonValue;
use App\Support\Http\Problems\InvalidOptionIdentifierProblem;
use App\Support\Http\Problems\InvalidOptionPayloadProblem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PutOptionRequest extends FormRequest
{
    private const KEY_PATTERN = '/^[a-z0-9_][a-z0-9_.-]{1,63}$/';

    private ?Option $resolvedOption = null;

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $option = $this->option();

        return $user->can('write', $option);
    }

    public function rules(): array
    {
        return [
            'namespace' => ['required', 'string', 'regex:' . self::KEY_PATTERN],
            'key' => ['required', 'string', 'regex:' . self::KEY_PATTERN],
            'value' => ['required', 'nullable', new JsonValue(maxBytes: 65536)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'namespace' => $this->route('namespace'),
            'key' => $this->route('key'),
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        $errors = $validator->errors();

        $code = 'INVALID_OPTION_PAYLOAD';
        if ($errors->has('value')) {
            $code = 'INVALID_JSON_VALUE';
        } elseif ($errors->has('namespace') || $errors->has('key')) {
            $code = 'INVALID_OPTION_IDENTIFIER';
        }

        if ($code === 'INVALID_OPTION_IDENTIFIER') {
            throw new InvalidOptionIdentifierProblem($errors->messages());
        }

        throw new InvalidOptionPayloadProblem($errors->messages(), $code);
    }

    public function option(): Option
    {
        if ($this->resolvedOption !== null) {
            return $this->resolvedOption;
        }

        $namespace = (string) $this->route('namespace');
        $key = (string) $this->route('key');

        $option = Option::withTrashed()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        if ($option === null) {
            $option = new Option([
                'namespace' => $namespace,
                'key' => $key,
            ]);
        }

        return $this->resolvedOption = $option;
    }
}

