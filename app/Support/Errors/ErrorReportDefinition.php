<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Throwable;

/**
 * @template T of Throwable
 */
final class ErrorReportDefinition
{
    /**
     * @param class-string<T>|null $throwableClass
     * @param Closure(T, ErrorPayload): array<string, mixed>|null $contextResolver
     */
    public function __construct(
        public readonly ?string $throwableClass,
        public readonly string $level,
        public readonly ?string $message,
        private readonly ?Closure $contextResolver = null,
    ) {
        if ($level === '' || trim($level) === '') {
            throw new InvalidArgumentException('Report level cannot be empty.');
        }
    }

    /**
     * @param Closure $resolver
     */
    public function bind(?Container $container): self
    {
        if ($this->contextResolver === null || $container === null) {
            return $this;
        }

        /** @var Closure $closure */
        $closure = $this->contextResolver->bindTo($container, $container);

        return new self(
            throwableClass: $this->throwableClass,
            level: $this->level,
            message: $this->message,
            contextResolver: $closure,
        );
    }

    /**
     * @param T $throwable
     * @return array<string, mixed>|null
     */
    public function resolveContext(Throwable $throwable, ErrorPayload $payload): ?array
    {
        if ($this->contextResolver === null) {
            return null;
        }

        /** @var Closure(T, ErrorPayload): array<string, mixed> $resolver */
        $resolver = $this->contextResolver;

        return $resolver($throwable, $payload);
    }
}

