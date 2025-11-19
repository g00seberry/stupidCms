<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Media;

use App\Domain\Media\Services\CollectionRulesResolver;
use App\Models\Media;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\HttpErrorException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Request для массовой загрузки медиа-файлов.
 *
 * Валидирует данные для массовой загрузки медиа-файлов:
 * - files: обязательный массив файлов (минимум 1, максимум 50 файлов)
 * - files.*: каждый файл должен соответствовать правилам (размер и MIME тип из конфига)
 * - collection: опциональная коллекция (автоматически slugify, regex, максимум 64 символа)
 * - title: опциональный заголовок для всех файлов (минимум 1, максимум 255 символов)
 * - alt: опциональный alt текст для всех файлов (минимум 1, максимум 255 символов)
 *
 * Автоматически нормализует collection через slugify для очистки данных на границе.
 *
 * @package App\Http\Requests\Admin\Media
 */
class BulkStoreMediaRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Требует права create для Media.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Media::class) ?? false;
    }

    /**
     * Подготовить данные для валидации.
     *
     * Сохраняет оригинальное значение collection для валидации до нормализации.
     * Нормализует пустые строки в null для title и alt, чтобы они не проходили валидацию min:1.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Нормализация title: пустые строки → null
        if ($this->has('title') && is_string($this->input('title'))) {
            $title = trim($this->input('title'));
            $this->merge(['title' => $title !== '' ? $title : null]);
        }

        // Нормализация alt: пустые строки → null
        if ($this->has('alt') && is_string($this->input('alt'))) {
            $alt = trim($this->input('alt'));
            $this->merge(['alt' => $alt !== '' ? $alt : null]);
        }

        // Сохраняем оригинальное значение collection для валидации
        // Нормализация будет выполнена после валидации в методе withValidator
        if ($this->has('collection') && is_string($this->input('collection'))) {
            $collection = trim($this->input('collection'));
            if ($collection === '') {
                $this->merge(['collection' => null]);
            }
        }
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - files: обязательный массив файлов (минимум 1, максимум 50 файлов)
     * - files.*: каждый файл должен соответствовать правилам (размер и MIME тип из конфига)
     * - title: опциональный заголовок (минимум 1, максимум 255 символов, если указан)
     * - alt: опциональный alt текст (минимум 1, максимум 255 символов, если указан)
     * - collection: опциональная коллекция (regex, максимум 64 символа, автоматически slugify)
     *
     * Правила валидации файлов берутся из конфигурации коллекции, если она указана,
     * иначе используются глобальные значения.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CollectionRulesResolver $resolver */
        $resolver = app(CollectionRulesResolver::class);
        // Используем оригинальное значение collection для получения правил
        // После валидации оно будет нормализовано
        $collection = $this->input('collection');

        $rules = $resolver->getRules($collection);
        $maxUploadKb = (int) ($rules['max_size_bytes'] / 1024);
        $allowedMimes = implode(',', $rules['allowed_mimes'] ?? config('media.allowed_mimes', []));

        return [
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => ['required', 'file', "max:{$maxUploadKb}", "mimetypes:{$allowedMimes}"],
            'title' => 'nullable|filled|string|min:1|max:255',
            'alt' => 'nullable|filled|string|min:1|max:255',
            'collection' => ['nullable', 'string', 'max:64', function ($attribute, $value, $fail) {
                if ($value === null) {
                    return;
                }
                // Валидируем оригинальное значение: проверяем, что оно содержит только допустимые символы
                // Допустимые символы: буквы, цифры, пробелы (будут заменены на дефисы), дефисы, подчеркивания, точки
                // Недопустимые: специальные символы типа !, @, #, $, %, ^, &, *, (, ), +, =, [, ], {, }, |, \, :, ;, ", ', <, >, ?, /, ~, `
                if (!preg_match('/^[a-zA-Z0-9\s\-_.]+$/', $value)) {
                    $fail('The collection must contain only letters, numbers, spaces, hyphens, underscores, and dots.');
                }
                // Также проверяем, что после slugify значение не пустое и соответствует regex
                $normalized = Str::slug($value, '-');
                if ($normalized === '' || !preg_match('/^[a-z0-9-_.]+$/i', $normalized)) {
                    $fail('The collection must be able to be normalized to a valid slug.');
                }
            }],
        ];
    }

    /**
     * Настроить валидатор после создания правил.
     *
     * Нормализует collection через slugify после успешной валидации.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator Валидатор
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Нормализуем collection после валидации, только если нет ошибок для collection
            if (!$validator->errors()->has('collection') && $this->has('collection') && is_string($this->input('collection'))) {
                $collection = trim($this->input('collection'));
                if ($collection !== '') {
                    // Используем replace() для обновления данных после валидации
                    $data = $this->all();
                    $data['collection'] = Str::slug($collection, '-');
                    $this->replace($data);
                }
            }
        });
    }

    /**
     * Получить валидированные данные запроса.
     *
     * Нормализует collection через slugify после валидации.
     *
     * @param string|array<string>|null $key Ключ для получения (опционально)
     * @param mixed $default Значение по умолчанию (опционально)
     * @return array<string, mixed>|mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Если запрошен конкретный ключ, возвращаем как есть
        if ($key !== null) {
            return $validated;
        }
        
        // Нормализуем collection после валидации
        if (isset($validated['collection']) && is_string($validated['collection'])) {
            $collection = trim($validated['collection']);
            if ($collection !== '') {
                $validated['collection'] = Str::slug($collection, '-');
            } else {
                $validated['collection'] = null;
            }
        }
        
        return $validated;
    }

    /**
     * Получить кастомные сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'The files field is required.',
            'files.array' => 'The files must be an array.',
            'files.min' => 'At least one file is required.',
            'files.max' => 'Maximum 50 files allowed per request.',
            'files.*.required' => 'Each file is required.',
            'files.*.file' => 'Each file must be a valid file.',
        ];
    }

    /**
     * Обработать ошибки валидации.
     *
     * Выбрасывает HttpErrorException с кодом VALIDATION_ERROR.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator Валидатор
     * @return void
     * @throws \App\Support\Errors\HttpErrorException
     */
    protected function failedValidation(Validator $validator): void
    {
        /** @var ErrorFactory $factory */
        $factory = app(ErrorFactory::class);

        $payload = $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail('The bulk media upload payload failed validation constraints.')
            ->meta(['errors' => $validator->errors()->messages()])
            ->build();

        throw new HttpErrorException($payload);
    }
}

