<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use LogicException;

/**
 * Исключение: конфликт full_path при встраивании blueprint.
 *
 * Выбрасывается, когда материализация создаст path с full_path,
 * который уже существует в host blueprint.
 */
class PathConflictException extends LogicException implements ErrorConvertible
{
    /**
     * Создать исключение для конфликта путей.
     *
     * @param string $hostCode Код host blueprint
     * @param string $embeddedCode Код embedded blueprint
     * @param array<string> $conflictingPaths Список конфликтующих путей
     * @return self
     */
    public static function create(
        string $hostCode,
        string $embeddedCode,
        array $conflictingPaths
    ): self {
        $pathsList = implode(', ', array_map(fn($p) => "'$p'", $conflictingPaths));
        
        return new self(
            $hostCode,
            $embeddedCode,
            $conflictingPaths,
            "Невозможно встроить blueprint '{$embeddedCode}' в '{$hostCode}': " .
            "конфликт путей: {$pathsList}. " .
            "Переименуйте поля или измените host_path."
        );
    }

    /**
     * @param string $hostCode Код host blueprint
     * @param string $embeddedCode Код embedded blueprint
     * @param array<string> $conflictingPaths Список конфликтующих путей
     * @param string $message Сообщение об ошибке
     */
    private function __construct(
        public readonly string $hostCode,
        public readonly string $embeddedCode,
        public readonly array $conflictingPaths,
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
                'conflicting_paths' => $this->conflictingPaths,
            ])
            ->build();
    }
}

