<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use LogicException;

/**
 * Исключение: попытка редактировать скопированное поле Path.
 *
 * Выбрасывается при попытке изменить скопированное поле (материализованное из другого blueprint).
 */
class CannotEditCopiedPathException extends LogicException implements ErrorConvertible
{
    /**
     * Создать исключение для попытки редактировать скопированное поле.
     *
     * @param string $pathFullPath Полный путь поля
     * @param string $sourceBlueprintCode Код исходного blueprint
     * @return self
     */
    public static function create(string $pathFullPath, string $sourceBlueprintCode): self
    {
        $message = "Невозможно редактировать скопированное поле '{$pathFullPath}'. " .
            "Измените исходное поле в blueprint '{$sourceBlueprintCode}'.";

        return new self($pathFullPath, $sourceBlueprintCode, $message);
    }

    /**
     * @param string $pathFullPath Полный путь поля
     * @param string $sourceBlueprintCode Код исходного blueprint
     * @param string $message Сообщение об ошибке
     */
    private function __construct(
        public readonly string $pathFullPath,
        public readonly string $sourceBlueprintCode,
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
                'source_blueprint_code' => $this->sourceBlueprintCode,
            ])
            ->build();
    }
}

