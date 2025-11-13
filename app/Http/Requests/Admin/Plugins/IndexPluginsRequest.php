<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Plugins;

use App\Models\Plugin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для получения списка плагинов в админ-панели.
 *
 * Валидирует параметры фильтрации, поиска, сортировки и пагинации
 * для списка плагинов.
 *
 * @package App\Http\Requests\Admin\Plugins
 */
final class IndexPluginsRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Требует права viewAny для Plugin.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Plugin::class) ?? false;
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - q: опциональный поисковый запрос (максимум 128 символов)
     * - enabled: опциональный фильтр по статусу (true, false, any)
     * - page/per_page: опциональные параметры пагинации
     * - sort: опциональная сортировка (name, slug, version, updated_at)
     * - order: опциональный порядок (asc, desc)
     *
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
     * Получить валидированные данные с значениями по умолчанию.
     *
     * Добавляет значения по умолчанию для enabled, sort, order, per_page.
     *
     * @param string|null $key Ключ для извлечения конкретного значения
     * @param mixed $default Значение по умолчанию
     * @return array<string, mixed> Валидированные данные с значениями по умолчанию
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

