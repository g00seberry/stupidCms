<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение: плагин уже отключён.
 *
 * Выбрасывается при попытке отключить плагин, который уже отключён.
 *
 * @package App\Domain\Plugins\Exceptions
 */
final class PluginAlreadyDisabledException extends RuntimeException implements ErrorConvertible
{
    /**
     * @param string $slug Slug плагина
     * @param string $message Сообщение об ошибке
     */
    private function __construct(
        public readonly string $slug,
        string $message
    ) {
        parent::__construct($message);
    }

    /**
     * Создать исключение для указанного slug.
     *
     * @param string $slug Slug плагина
     * @return self Исключение
     */
    public static function forSlug(string $slug): self
    {
        return new self(
            $slug,
            sprintf('Plugin "%s" already disabled.', $slug)
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
        return $factory->for(ErrorCode::PLUGIN_ALREADY_DISABLED)
            ->detail(sprintf('Plugin %s is already disabled.', $this->slug))
            ->meta([
                'slug' => $this->slug,
            ])
            ->build();
    }
}

