<?php

declare(strict_types=1);

namespace App\Support\Errors;

use App\Contracts\ErrorConvertible;
use Closure;
use Illuminate\Contracts\Container\Container;
use function is_string;
use Throwable;

final class ErrorKernel
{
    /**
     * @var list<ErrorMapping>
     */
    private readonly array $mappings;

    /**
     * @param Closure(Throwable, ErrorFactory): ErrorPayload $fallback
     */
    public function __construct(
        private readonly ErrorFactory $factory,
        private readonly Closure $fallback,
        private readonly ?ErrorReportDefinition $fallbackReport,
        ErrorMapping ...$mappings,
    ) {
        $this->mappings = $mappings;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config, ?Container $container = null): self
    {
        $typesConfig = $config['types'] ?? [];
        $catalog = ErrorCatalog::fromConfig($typesConfig);
        $factory = new ErrorFactory($catalog);

        $mappingsConfig = $config['mappings'] ?? [];
        $mappings = [];

        foreach ($mappingsConfig as $class => $definition) {
            $builder = $definition['builder'] ?? null;

            if (! $builder instanceof Closure) {
                throw new \InvalidArgumentException(sprintf(
                    'Error mapping for "%s" must define a builder closure.',
                    $class,
                ));
            }

            $builder = self::bindClosure($builder, $container);

            $reportDefinition = self::buildReportDefinition(
                throwableClass: (string) $class,
                definition: $definition['report'] ?? null,
                container: $container,
            );

            $mappings[] = new ErrorMapping(
                throwableClass: (string) $class,
                builder: $builder,
                reportDefinition: $reportDefinition,
            );
        }

        $fallback = $config['fallback']['builder'] ?? null;

        if (! $fallback instanceof Closure) {
            throw new \InvalidArgumentException('Fallback builder must be a closure.');
        }

        $fallback = self::bindClosure($fallback, $container);

        $fallbackReport = self::buildReportDefinition(
            throwableClass: null,
            definition: $config['fallback']['report'] ?? null,
            container: $container,
        );

        return new self($factory, $fallback, $fallbackReport, ...$mappings);
    }

    public function resolve(Throwable $throwable): ErrorPayload
    {
        if ($throwable instanceof ErrorConvertible) {
            $payload = $throwable->toError($this->factory);
            ErrorReporter::report($throwable, $payload, null);

            return $payload;
        }

        $reportDefinition = $this->fallbackReport;

        foreach ($this->mappings as $mapping) {
            if ($mapping->matches($throwable)) {
                $payload = $mapping->build($throwable, $this->factory);
                $reportDefinition = $mapping->reportDefinition() ?? $reportDefinition;

                ErrorReporter::report($throwable, $payload, $reportDefinition);

                return $payload;
            }
        }

        $fallback = $this->fallback;

        $payload = $fallback($throwable, $this->factory);

        ErrorReporter::report($throwable, $payload, $reportDefinition);

        return $payload;
    }

    public function factory(): ErrorFactory
    {
        return $this->factory;
    }

    /**
     * @param Closure(Throwable, ErrorFactory): ErrorPayload $fallback
     */
    public function withFallback(Closure $fallback): self
    {
        return new self($this->factory, $fallback, $this->fallbackReport, ...$this->mappings);
    }

    /**
     * @param Closure $closure
     */
    private static function bindClosure(Closure $closure, ?Container $container): Closure
    {
        if ($container === null) {
            return $closure;
        }

        return $closure->bindTo($container, $container);
    }

    /**
     * @param array<string, mixed>|null $definition
     */
    private static function buildReportDefinition(?string $throwableClass, ?array $definition, ?Container $container): ?ErrorReportDefinition
    {
        if ($definition === null) {
            return null;
        }

        $level = $definition['level'] ?? 'error';

        if (! is_string($level)) {
            throw new \InvalidArgumentException('Report level must be a string.');
        }

        $message = $definition['message'] ?? null;

        if ($message !== null && ! is_string($message)) {
            throw new \InvalidArgumentException('Report message must be a string or null.');
        }

        $context = $definition['context'] ?? null;

        if ($context !== null && ! $context instanceof Closure) {
            throw new \InvalidArgumentException('Report context must be a closure.');
        }

        $definition = new ErrorReportDefinition(
            throwableClass: $throwableClass,
            level: $level,
            message: $message,
            contextResolver: $context,
        );

        return $definition->bind($container);
    }
}

