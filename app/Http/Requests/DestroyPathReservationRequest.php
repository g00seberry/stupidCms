<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyPathReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source' => 'required|string|max:100',
            'path' => 'nullable|string|max:255', // Опционально из body, если не в URL
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
            'source.required' => 'The source field is required.',
            'source.max' => 'The source may not be greater than 100 characters.',
            'path.max' => 'The path may not be greater than 255 characters.',
        ];
    }

    /**
     * Get the path from either route parameter or request body.
     */
    public function getPath(): string
    {
        return $this->route('path') ?? $this->input('path', '');
    }
}

