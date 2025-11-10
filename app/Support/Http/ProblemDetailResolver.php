<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Contracts\ProblemConvertible;
use Throwable;

final class ProblemDetailResolver
{
    private function __construct()
    {
    }

    public static function resolve(Throwable $throwable, ProblemType $type, bool $allowMessage = false): string
    {
        if ($throwable instanceof ProblemConvertible) {
            return $throwable->toProblem()->userFriendlyDetail();
        }

        $problem = self::findProblemException($throwable);

        if ($problem instanceof HttpProblemException) {
            return $problem->problem()->userFriendlyDetail();
        }

        $convertible = self::findProblemConvertible($throwable);

        if ($convertible instanceof ProblemConvertible) {
            return $convertible->toProblem()->userFriendlyDetail();
        }

        if ($allowMessage) {
            $message = trim($throwable->getMessage());

            if ($message !== '') {
                return $message;
            }
        }

        return $type->defaultDetail();
    }

    private static function findProblemException(Throwable $throwable): ?HttpProblemException
    {
        $current = $throwable;

        while ($current !== null) {
            if ($current instanceof HttpProblemException) {
                return $current;
            }

            $current = $current->getPrevious();
        }

        return null;
    }

    private static function findProblemConvertible(Throwable $throwable): ?ProblemConvertible
    {
        $current = $throwable->getPrevious();

        while ($current !== null) {
            if ($current instanceof ProblemConvertible) {
                return $current;
            }

            $current = $current->getPrevious();
        }

        return null;
    }
}
