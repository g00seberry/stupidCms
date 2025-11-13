<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\ReservedSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePostTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * 
     * Authorization is handled by route middleware (can:manage.posttypes).
     * This returns true to avoid duplicate checks.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $slug = $this->route('slug');

        return [
            'slug' => [
                'sometimes',
                'string',
                'max:64',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('post_types', 'slug')->ignore($slug, 'slug'),
                new ReservedSlug(),
            ],
            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'options_json' => [
                'present',
                'array',
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        $fail('The options_json field is required.');
                        return;
                    }

                    if (! is_array($value)) {
                        return;
                    }

                    if ($value === []) {
                        // Empty object is allowed and comes through as []
                        return;
                    }

                    if (array_is_list($value)) {
                        $fail('The options_json field must be an object.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, underscores, and hyphens.',
            'options_json.present' => 'The options_json field is required.',
            'options_json.array' => 'The options_json field must be an object.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Warnings не блокируют валидацию, только предупреждают
        // Реальная передача warnings происходит через метод warnings() в контроллере
    }

    /**
     * Получить warnings для добавления в meta ответа.
     *
     * @return array<string, array<string>>
     */
    public function warnings(): array
    {
        return [];
    }
}

