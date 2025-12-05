<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use LogicException;

/**
 * Исключение: попытка создать дубликат встраивания blueprint.
 *
 * Выбрасывается при попытке встроить blueprint в то же место, где он уже встроен.
 */
class DuplicateEmbedException extends LogicException implements ErrorConvertible
{
    /**
     * Создать исключение для дубликата встраивания.
     *
     * @param string $hostCode Код host blueprint (кто встраивает)
     * @param string $embeddedCode Код embedded blueprint (кого встраивают)
     * @param string|null $hostPathFullPath Полный путь host_path (null = корень)
     * @return self
     */
    public static function create(
        string $hostCode,
        string $embeddedCode,
        ?string $hostPathFullPath = null
    ): self {
        $hostName = $hostPathFullPath
            ? "под полем '{$hostPathFullPath}'"
            : "в корень";

        $message = "Blueprint '{$embeddedCode}' уже встроен в '{$hostCode}' {$hostName}.";

        return new self($hostCode, $embeddedCode, $hostPathFullPath, $message);
    }

    /**
     * @param string $hostCode Код host blueprint
     * @param string $embeddedCode Код embedded blueprint
     * @param string|null $hostPathFullPath Полный путь host_path
     * @param string $message Сообщение об ошибке
     */
    private function __construct(
        public readonly string $hostCode,
        public readonly string $embeddedCode,
        public readonly ?string $hostPathFullPath,
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
        return $factory->for(ErrorCode::CONFLICT)
            ->detail($this->getMessage())
            ->meta([
                'host_blueprint_code' => $this->hostCode,
                'embedded_blueprint_code' => $this->embeddedCode,
                'host_path_full_path' => $this->hostPathFullPath,
            ])
            ->build();
    }
}

