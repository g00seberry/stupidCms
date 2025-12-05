<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use LogicException;

/**
 * Исключение: дублирование full_path при создании path.
 *
 * Выбрасывается, когда пытаются создать path с full_path,
 * который уже существует в blueprint.
 */
class DuplicatePathException extends LogicException implements ErrorConvertible
{
    /**
     * Создать исключение для дублирования пути.
     *
     * @param string $fullPath Полный путь, который уже существует
     * @param string $blueprintCode Код blueprint
     * @return self
     */
    public static function create(string $fullPath, string $blueprintCode): self
    {
        return new self(
            $fullPath,
            $blueprintCode,
            "Путь '{$fullPath}' уже существует в blueprint '{$blueprintCode}'. " .
            "Используйте другое имя поля или удалите существующий путь."
        );
    }

    /**
     * @param string $fullPath Полный путь, который уже существует
     * @param string $blueprintCode Код blueprint
     * @param string $message Сообщение об ошибке
     */
    private function __construct(
        public readonly string $fullPath,
        public readonly string $blueprintCode,
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
                'full_path' => $this->fullPath,
                'blueprint_code' => $this->blueprintCode,
            ])
            ->build();
    }
}

