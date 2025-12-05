<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use LogicException;

/**
 * Исключение: невалидный host_path при встраивании blueprint.
 *
 * Выбрасывается при попытке встроить blueprint в невалидный host_path:
 * - host_path не принадлежит host blueprint
 * - host_path является скопированным полем
 * - host_path не является группой (data_type != 'json')
 */
class InvalidHostPathException extends LogicException implements ErrorConvertible
{
    /**
     * Создать исключение для host_path, не принадлежащего blueprint.
     *
     * @param string $hostPathFullPath Полный путь host_path
     * @param string $blueprintCode Код blueprint
     * @return self
     */
    public static function notOwnedByBlueprint(string $hostPathFullPath, string $blueprintCode): self
    {
        return new self(
            $hostPathFullPath,
            $blueprintCode,
            'not_owned',
            "host_path '{$hostPathFullPath}' не принадлежит blueprint '{$blueprintCode}'."
        );
    }

    /**
     * Создать исключение для скопированного host_path.
     *
     * @param string $hostPathFullPath Полный путь host_path
     * @return self
     */
    public static function isCopied(string $hostPathFullPath): self
    {
        return new self(
            $hostPathFullPath,
            null,
            'is_copied',
            "Нельзя встраивать в скопированное поле '{$hostPathFullPath}'. " .
            "Используйте собственные поля blueprint."
        );
    }

    /**
     * Создать исключение для host_path, который не является группой.
     *
     * @param string $hostPathFullPath Полный путь host_path
     * @return self
     */
    public static function notAGroup(string $hostPathFullPath): self
    {
        return new self(
            $hostPathFullPath,
            null,
            'not_a_group',
            "host_path '{$hostPathFullPath}' должен быть группой (data_type = 'json')."
        );
    }

    /**
     * @param string $hostPathFullPath Полный путь host_path
     * @param string|null $blueprintCode Код blueprint (null для ошибок, не связанных с принадлежностью)
     * @param string $reason Причина ошибки: 'not_owned', 'is_copied', 'not_a_group'
     * @param string $message Сообщение об ошибке
     */
    private function __construct(
        public readonly string $hostPathFullPath,
        public readonly ?string $blueprintCode,
        public readonly string $reason,
        string $message
    ) {
        parent::__construct($message);
    }

    /**
     * Преобразовать исключение в ErrorPayload.
     *
     * @param \App\Support\Errors\ErrorFactory $factory Фабрика ошибок
     * @return \App\Support\Errors\ErrorPayload Payload ошибки
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        $meta = [
            'host_path_full_path' => $this->hostPathFullPath,
            'reason' => $this->reason,
        ];

        if ($this->blueprintCode !== null) {
            $meta['blueprint_code'] = $this->blueprintCode;
        }

        return $factory->for(ErrorCode::BAD_REQUEST)
            ->detail($this->getMessage())
            ->meta($meta)
            ->build();
    }
}

