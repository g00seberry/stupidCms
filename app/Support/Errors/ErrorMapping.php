<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Closure;
use Throwable;

/**
 * @template T of Throwable
 */
final class ErrorMapping
{
    /**
     * @param class-string<T> $throwableClass
     * @param Closure(T, ErrorFactory): ErrorPayload $builder
     */
    public function __construct(
        public readonly string $throwableClass,
        private readonly Closure $builder,
        private readonly ?ErrorReportDefinition $reportDefinition = null,
    ) {
    }

    public function matches(Throwable $throwable): bool
    {
        return is_a($throwable, $this->throwableClass);
    }

    public function build(Throwable $throwable, ErrorFactory $factory): ErrorPayload
    {
        /** @var Closure(T, ErrorFactory): ErrorPayload $builder */
        $builder = $this->builder;

        return $builder($throwable, $factory);
    }

    public function reportDefinition(): ?ErrorReportDefinition
    {
        return $this->reportDefinition;
    }
}


