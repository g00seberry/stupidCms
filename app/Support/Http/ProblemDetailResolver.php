<?php

declare(strict_types=1);

namespace App\Support\Http;

use Throwable;

final class ProblemDetailResolver
{
    private function __construct()
    {
    }

    public static function resolve(Throwable $throwable, ProblemType $type, bool $allowMessage = false): string
    {
        $problem = self::findProblemException($throwable);

        if ($problem instanceof ProblemException) {
            return $problem->userFriendlyDetail();
        }

        if ($allowMessage) {
            $message = trim($throwable->getMessage());

            if ($message !== '') {
                return $message;
            }
        }

        return $type->defaultDetail();
    }

    private static function findProblemException(Throwable $throwable): ?ProblemException
    {
        $current = $throwable;

        while ($current !== null) {
            if ($current instanceof ProblemException) {
                return $current;
            }

            $current = $current->getPrevious();
        }

        return null;
    }
}
