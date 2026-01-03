<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Media;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: проверка соответствия MIME-типа медиа-файла
 * списку допустимых MIME-типов из constraints media-поля.
 *
 * Поддерживает как одиночные значения (cardinality=one), так и массивы (cardinality=many).
 * Значения должны быть ULID строками, которые ссылаются на записи в таблице media.
 *
 * @package App\Rules
 */
final class MediaMime implements ValidationRule
{
    /**
     * @param array<string> $allowedMimeTypes Список допустимых MIME-типов (например, ['image/jpeg', 'image/png'])
     * @param string $pathFullPath Полный путь к media-полю (для сообщений об ошибках)
     */
    public function __construct(
        private readonly array $allowedMimeTypes,
        private readonly string $pathFullPath
    ) {}

    /**
     * Выполнить правило валидации.
     *
     * Проверяет, что значение (ULID строки медиа-файлов) ссылается на Media записи,
     * у которых MIME-тип входит в список допустимых.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации (string для cardinality=one, array<string> для cardinality=many)
     * @param \Closure(string, string): void $fail Callback для добавления ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Если значение null, пропускаем валидацию (required/nullable обработают это)
        if ($value === null) {
            return;
        }

        // Собираем все mediaId для валидации
        $mediaIds = [];
        $mediaIdToAttribute = []; // Маппинг mediaId -> attribute для ошибок

        // Обрабатываем одиночное значение (cardinality=one)
        if (is_string($value) && $value !== '') {
            $mediaIds[] = $value;
            $mediaIdToAttribute[$value] = $attribute;
        }
        // Обрабатываем массив значений (cardinality=many)
        elseif (is_array($value)) {
            foreach ($value as $index => $mediaId) {
                if (!is_string($mediaId) || $mediaId === '') {
                    continue; // Пропускаем невалидные значения (другие правила обработают это)
                }

                $mediaIds[] = $mediaId;
                $mediaIdToAttribute[$mediaId] = "{$attribute}.{$index}";
            }
        } else {
            // Неподдерживаемый тип значения - пропускаем (другие правила обработают это)
            return;
        }

        // Если нет mediaId для проверки, выходим
        if (empty($mediaIds)) {
            return;
        }

        // Загружаем все Media одним запросом для оптимизации (избегаем N+1)
        $mediaRecords = Media::whereIn('id', array_unique($mediaIds))
            ->get()
            ->keyBy('id');

        // Валидируем каждый Media
        foreach ($mediaIds as $mediaId) {
            $media = $mediaRecords->get($mediaId);

            // Если Media не найден, пропускаем валидацию (exists правило обработает это)
            if ($media === null) {
                continue;
            }

            // Проверяем, что MIME-тип входит в список допустимых
            if (!in_array($media->mime, $this->allowedMimeTypes, true)) {
                $allowedMimes = implode(', ', $this->allowedMimeTypes);
                $fail("The {$mediaIdToAttribute[$mediaId]} must reference a media file with MIME type in: {$allowedMimes}.");
            }
        }
    }
}

