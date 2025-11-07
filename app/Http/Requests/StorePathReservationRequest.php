<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePathReservationRequest extends FormRequest
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
            'path' => 'required|string|max:255',
            'source' => 'required|string|max:100',
            'reason' => 'nullable|string|max:255',
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
            'path.required' => 'The path field is required.',
            'path.max' => 'The path may not be greater than 255 characters.',
            'source.required' => 'The source field is required.',
            'source.max' => 'The source may not be greater than 100 characters.',
            'reason.max' => 'The reason may not be greater than 255 characters.',
        ];
    }
}

