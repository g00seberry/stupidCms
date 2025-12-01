<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use Exception;

/**
 * Исключение: невалидное правило валидации.
 *
 * Выбрасывается при попытке использовать правило валидации,
 * которое не проходит проверку формата или структуры.
 *
 * @package App\Domain\Blueprint\Validation\Exceptions
 */
final class InvalidValidationRuleException extends Exception implements ErrorConvertible
{
    /**
     * @param string $message Сообщение об ошибке
     * @param int $code Код ошибки
     * @param \Throwable|null $previous Предыдущее исключение
     */
    public function __construct(string $message = "Invalid validation rule", int $code = 0, ?\Throwable $previous = null)
    {
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
        return $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail($this->getMessage())
            ->build();
    }
}

