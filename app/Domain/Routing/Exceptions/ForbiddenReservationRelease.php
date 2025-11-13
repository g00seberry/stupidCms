<?php

declare(strict_types=1);

namespace App\Domain\Routing\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use Exception;

/**
 * Исключение: запрещено освобождать путь, зарезервированный другим источником.
 *
 * Выбрасывается при попытке освободить путь, который зарезервирован другим источником.
 *
 * @package App\Domain\Routing\Exceptions
 */
class ForbiddenReservationRelease extends Exception implements ErrorConvertible
{
    /**
     * @param string $path Нормализованный путь
     * @param string $owner Источник, который зарезервировал путь
     * @param string $attemptedSource Источник, который пытался освободить путь
     * @param string $message Сообщение об ошибке (генерируется автоматически, если пустое)
     * @param int $code Код ошибки
     * @param \Throwable|null $previous Предыдущее исключение
     */
    public function __construct(
        public readonly string $path,
        public readonly string $owner,
        public readonly string $attemptedSource,
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        if ($message === "") {
            $message = "Cannot release path '{$path}' reserved by '{$owner}' (attempted by '{$attemptedSource}')";
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * Преобразовать исключение в ErrorPayload.
     *
     * @param \App\Support\Errors\ErrorFactory $factory Фабрика ошибок
     * @return \App\Support\Errors\ErrorPayload Payload ошибки
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::FORBIDDEN)
            ->detail($this->getMessage())
            ->meta([
                'path' => $this->path,
                'owner' => $this->owner,
                'attempted_source' => $this->attemptedSource,
            ])
            ->build();
    }
}

