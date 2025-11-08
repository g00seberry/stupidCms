<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Plugins;

use App\Models\Plugin;
use Illuminate\Foundation\Http\FormRequest;

final class IndexPluginsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Plugin::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:128'],
            'enabled' => ['nullable', 'string', 'in:true,false,any'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:name,slug,version,updated_at'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        return array_merge([
            'enabled' => 'any',
            'sort' => 'name',
            'order' => 'asc',
            'per_page' => 25,
        ], $data);
    }
}

