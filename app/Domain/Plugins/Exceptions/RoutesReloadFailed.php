<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use RuntimeException;
use Throwable;

/**
 * Исключение: не удалось перезагрузить маршруты плагинов.
 *
 * Выбрасывается при ошибке во время перезагрузки маршрутов плагинов
 * (ошибка регистрации провайдеров, кэширования маршрутов и т.д.).
 *
 * @package App\Domain\Plugins\Exceptions
 */
final class RoutesReloadFailed extends RuntimeException implements ErrorConvertible
{
    /**
     * Создать исключение из предыдущего исключения.
     *
     * @param \Throwable $previous Предыдущее исключение
     * @return self Исключение
     */
    public static function from(Throwable $previous): self
    {
        return new self('Failed to reload plugin routes.', 0, $previous);
    }

    /**
     * Преобразовать исключение в ErrorPayload.
     *
     * @param \App\Support\Errors\ErrorFactory $factory Фабрика ошибок
     * @return \App\Support\Errors\ErrorPayload Payload ошибки
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::ROUTES_RELOAD_FAILED)
            ->detail($this->getMessage())
            ->build();
    }
}

