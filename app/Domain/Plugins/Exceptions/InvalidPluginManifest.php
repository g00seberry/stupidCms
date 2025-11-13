<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение: невалидный манифест плагина.
 *
 * Выбрасывается при обнаружении ошибок в манифесте плагина (отсутствие файла,
 * невалидный JSON, отсутствие обязательных полей и т.д.).
 *
 * @package App\Domain\Plugins\Exceptions
 */
final class InvalidPluginManifest extends RuntimeException implements ErrorConvertible
{
    /**
     * @param string $path Путь к манифесту плагина
     * @param string $reason Причина ошибки
     * @param string $message Сообщение об ошибке
     */
    private function __construct(
        public readonly string $path,
        public readonly string $reason,
        string $message
    ) {
        parent::__construct($message);
    }

    /**
     * Создать исключение для указанного пути.
     *
     * @param string $path Путь к манифесту
     * @param string $reason Причина ошибки
     * @return self Исключение
     */
    public static function forPath(string $path, string $reason): self
    {
        return new self(
            $path,
            $reason,
            sprintf('Invalid plugin manifest at "%s": %s', $path, $reason)
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
        return $factory->for(ErrorCode::INVALID_PLUGIN_MANIFEST)
            ->detail($this->getMessage())
            ->meta([
                'path' => $this->path,
                'reason' => $this->reason,
            ])
            ->build();
    }
}

