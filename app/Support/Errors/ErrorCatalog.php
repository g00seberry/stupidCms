<?php

declare(strict_types=1);

namespace App\Support\Errors;

use InvalidArgumentException;

/**
 * Registry of error types keyed by ErrorCode.
 *
 * @psalm-type ErrorTypeConfig = array{
 *     uri: string,
 *     title: string,
 *     status: int,
 *     detail: string
 * }
 */
final class ErrorCatalog
{
    /**
     * @var array<ErrorCode, ErrorType>
     */
    private array $types;

    /**
     * @param array<ErrorCode, ErrorType> $types
     */
    private function __construct(array $types)
    {
        $this->types = $types;
    }

    /**
     * @param array<string, ErrorTypeConfig> $config
     */
    public static function fromConfig(array $config): self
    {
        $types = [];

        foreach ($config as $code => $definition) {
            $enum = self::codeFromString($code);

            $types[$enum] = new ErrorType(
                code: $enum,
                uri: $definition['uri'],
                title: $definition['title'],
                status: $definition['status'],
                defaultDetail: $definition['detail'],
            );
        }

        return new self($types);
    }

    public function get(ErrorCode $code): ErrorType
    {
        if (! isset($this->types[$code])) {
            throw new InvalidArgumentException(sprintf(
                'Error type for code "%s" is not defined.',
                $code->value,
            ));
        }

        return $this->types[$code];
    }

    public function has(ErrorCode $code): bool
    {
        return isset($this->types[$code]);
    }

    public function all(): array
    {
        return $this->types;
    }

    private static function codeFromString(string $code): ErrorCode
    {
        return ErrorCode::from($code);
    }
}

