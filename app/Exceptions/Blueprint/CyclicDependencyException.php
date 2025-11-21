<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use LogicException;

/**
 * Исключение: попытка создать циклическую зависимость между blueprint'ами.
 *
 * Выбрасывается при попытке встроить blueprint A в B,
 * если B уже зависит от A (прямо или транзитивно).
 */
class CyclicDependencyException extends LogicException implements ErrorConvertible
{
    /**
     * Создать исключение для попытки встроить blueprint в самого себя.
     *
     * @param string $blueprintCode Код blueprint
     * @return self
     */
    public static function selfEmbed(string $blueprintCode): self
    {
        return new self($blueprintCode, null, "Нельзя встроить blueprint '{$blueprintCode}' в самого себя.");
    }

    /**
     * Создать исключение для циклической зависимости.
     *
     * @param string $hostCode Код host blueprint (кто встраивает)
     * @param string $embeddedCode Код embedded blueprint (кого встраивают)
     * @return self
     */
    public static function circularDependency(string $hostCode, string $embeddedCode): self
    {
        return new self(
            $hostCode,
            $embeddedCode,
            "Циклическая зависимость: '{$embeddedCode}' уже зависит от '{$hostCode}' " .
            "(прямо или транзитивно). Встраивание невозможно."
        );
    }

    /**
     * @param string $hostCode Код host blueprint (кто встраивает)
     * @param string|null $embeddedCode Код embedded blueprint (кого встраивают, null для self-embed)
     * @param string $message Сообщение об ошибке
     */
    private function __construct(
        public readonly string $hostCode,
        public readonly ?string $embeddedCode,
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
            'host_blueprint_code' => $this->hostCode,
        ];

        if ($this->embeddedCode !== null) {
            $meta['embedded_blueprint_code'] = $this->embeddedCode;
        }

        return $factory->for(ErrorCode::CONFLICT)
            ->detail($this->getMessage())
            ->meta($meta)
            ->build();
    }
}

