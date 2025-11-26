<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

/**
 * Ошибка валидации с доменной семантикой.
 *
 * Содержит структурированную информацию об ошибке валидации:
 * - путь поля
 * - код ошибки (для локализации и обработки на фронтенде)
 * - параметры ошибки
 * - текстовое сообщение (опционально)
 *
 * @package App\Domain\Blueprint\Validation
 */
final class ValidationError
{
    /**
     * @param string $field Путь поля в точечной нотации (например, 'content_json.title')
     * @param string $code Код ошибки (например, 'BLUEPRINT_REQUIRED', 'BLUEPRINT_MIN_LENGTH')
     * @param array<string, mixed> $params Параметры ошибки (например, ['min' => 1, 'max' => 500])
     * @param string|null $message Текстовое сообщение об ошибке (опционально)
     * @param int|null $pathId ID Path из Blueprint (опционально, для связи с определением поля)
     */
    public function __construct(
        public readonly string $field,
        public readonly string $code,
        public readonly array $params = [],
        public readonly ?string $message = null,
        public readonly ?int $pathId = null
    ) {}

    /**
     * Получить значение параметра ошибки.
     *
     * @param string $key Ключ параметра
     * @param mixed $default Значение по умолчанию, если параметр отсутствует
     * @return mixed
     */
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Проверить, есть ли параметр.
     *
     * @param string $key Ключ параметра
     * @return bool
     */
    public function hasParam(string $key): bool
    {
        return isset($this->params[$key]);
    }
}

