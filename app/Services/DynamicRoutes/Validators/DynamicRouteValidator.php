<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Validators;

final class DynamicRouteValidator
{
    public function isPrefixReserved(string $prefix): bool
    {
        $prefix = trim($prefix, '/');

        foreach (config('dynamic-routes.reserved_prefixes', []) as $r) {
            $r = trim((string) $r, '/');
            if ($r !== '' && ($prefix === $r || str_starts_with($prefix, $r . '/'))) {
                return true;
            }
        }

        return false;
    }
}
