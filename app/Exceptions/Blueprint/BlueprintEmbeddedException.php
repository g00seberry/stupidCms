<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use LogicException;

/**
 * Исключение: попытка удалить blueprint, который встроен в другие blueprint.
 *
 * Выбрасывается при попытке удалить blueprint, который используется как embedded в других blueprint.
 */
class BlueprintEmbeddedException extends LogicException implements ErrorConvertible
{
    /**
     * Создать исключение для blueprint, встроенного в другие blueprint.
     *
     * @param string $blueprintCode Код blueprint
     * @param int $embedsCount Количество встраиваний
     * @return self
     */
    public static function create(string $blueprintCode, int $embedsCount): self
    {
        $message = "Невозможно удалить blueprint '{$blueprintCode}': " .
            "встроен в другие blueprint. Сначала удалите встраивания.";

        return new self($blueprintCode, $embedsCount, $message);
    }

    /**
     * @param string $blueprintCode Код blueprint
     * @param int $embedsCount Количество встраиваний
     * @param string $message Сообщение об ошибке
     */
    private function __construct(
        public readonly string $blueprintCode,
        public readonly int $embedsCount,
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
                'blueprint_code' => $this->blueprintCode,
                'embeds_count' => $this->embedsCount,
            ])
            ->build();
    }
}

