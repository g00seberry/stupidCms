<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use LogicException;

/**
 * Исключение: попытка удалить скопированное поле Path.
 *
 * Выбрасывается при попытке удалить скопированное поле (материализованное из другого blueprint).
 */
class CannotDeleteCopiedPathException extends LogicException implements ErrorConvertible
{
    /**
     * Создать исключение для попытки удалить скопированное поле.
     *
     * @param string $pathFullPath Полный путь поля
     * @param string $hostBlueprintCode Код host blueprint
     * @return self
     */
    public static function create(string $pathFullPath, string $hostBlueprintCode): self
    {
        $message = "Невозможно удалить скопированное поле '{$pathFullPath}'. " .
            "Удалите встраивание в blueprint '{$hostBlueprintCode}'.";

        return new self($pathFullPath, $hostBlueprintCode, $message);
    }

    /**
     * @param string $pathFullPath Полный путь поля
     * @param string $hostBlueprintCode Код host blueprint
     * @param string $message Сообщение об ошибке
     */
    private function __construct(
        public readonly string $pathFullPath,
        public readonly string $hostBlueprintCode,
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
        return $factory->for(ErrorCode::FORBIDDEN)
            ->detail($this->getMessage())
            ->meta([
                'path_full_path' => $this->pathFullPath,
                'host_blueprint_code' => $this->hostBlueprintCode,
            ])
            ->build();
    }
}

