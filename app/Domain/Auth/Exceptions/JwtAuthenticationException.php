<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение: ошибка JWT аутентификации.
 *
 * Выбрасывается при неудачной попытке аутентификации через JWT токен.
 *
 * @package App\Domain\Auth\Exceptions
 */
final class JwtAuthenticationException extends RuntimeException implements ErrorConvertible
{
    /**
     * @param string $reason Причина ошибки (например, 'token_expired', 'token_invalid')
     * @param string $detail Детальное описание ошибки
     */
    public function __construct(
        public readonly string $reason,
        public readonly string $detail,
    ) {
        parent::__construct(
            sprintf('JWT authentication failed: %s (%s)', $reason, $detail),
        );
    }

    /**
     * Преобразовать исключение в ErrorPayload.
     *
     * @param \App\Support\Errors\ErrorFactory $factory Фабрика ошибок
     * @return \App\Support\Errors\ErrorPayload Payload ошибки
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::UNAUTHORIZED)
            ->detail('Authentication is required to access this resource.')
            ->meta([
                'reason' => $this->reason,
                'detail' => $this->detail,
            ])
            ->build();
    }
}
