<?php

namespace App\Http\Requests\Admin\Options;

use App\Models\Option;
use App\Rules\JsonValue;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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

        $payload = [
            'type' => 'https://stupidcms.dev/problems/validation-error',
            'title' => 'Validation error',
            'status' => 422,
            'detail' => 'Invalid option payload.',
            'code' => $code,
            'errors' => $errors->messages(),
        ];

        $response = response()->json($payload, 422);
        $response->headers->set('Content-Type', 'application/problem+json');

        throw new HttpResponseException($response);
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

