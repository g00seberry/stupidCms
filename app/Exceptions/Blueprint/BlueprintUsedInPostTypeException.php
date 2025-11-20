<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use LogicException;

/**
 * Исключение: попытка удалить blueprint, который используется в PostType.
 *
 * Выбрасывается при попытке удалить blueprint, который привязан к одному или нескольким PostType.
 */
class BlueprintUsedInPostTypeException extends LogicException implements ErrorConvertible
{
    /**
     * Создать исключение для blueprint, используемого в PostType.
     *
     * @param string $blueprintCode Код blueprint
     * @param int $postTypesCount Количество PostType, использующих blueprint
     * @return self
     */
    public static function create(string $blueprintCode, int $postTypesCount): self
    {
        $message = "Невозможно удалить blueprint '{$blueprintCode}': " .
            "используется в PostType. Сначала отвяжите PostType от blueprint.";

        return new self($blueprintCode, $postTypesCount, $message);
    }

    /**
     * @param string $blueprintCode Код blueprint
     * @param int $postTypesCount Количество PostType
     * @param string $message Сообщение об ошибке
     */
    private function __construct(
        public readonly string $blueprintCode,
        public readonly int $postTypesCount,
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
                'post_types_count' => $this->postTypesCount,
            ])
            ->build();
    }
}

