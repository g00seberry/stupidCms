<?php

declare(strict_types=1);

namespace App\Domain\Media\Validation;

use RuntimeException;

/**
 * Исключение при валидации медиа-файла.
 *
 * Выбрасывается валидаторами при обнаружении проблем с файлом
 * (неверный MIME, corruption, превышение ограничений и т.д.).
 *
 * @package App\Domain\Media\Validation
 */
class MediaValidationException extends RuntimeException
{
    /**
     * @param string $message Сообщение об ошибке
     * @param string|null $validator Имя валидатора, вызвавшего исключение
     * @param int $code Код ошибки
     * @param \Throwable|null $previous Предыдущее исключение
     */
    public function __construct(
        string $message,
        private readonly ?string $validator = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Получить имя валидатора, вызвавшего исключение.
     *
     * @return string|null
     */
    public function getValidator(): ?string
    {
        return $this->validator;
    }
}

