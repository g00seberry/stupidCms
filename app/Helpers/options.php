<?php

if (! function_exists('options')) {
    /** Удобное чтение опций: options('site','home_entry_id', null) */
    function options(string $ns, string $key, mixed $default = null): mixed
    {
        return app(\App\Domain\Options\OptionsRepository::class)->get($ns, $key, $default);
    }
}

if (! function_exists('option_set')) {
    function option_set(string $ns, string $key, mixed $value): void
    {
        app(\App\Domain\Options\OptionsRepository::class)->set($ns, $key, $value);
    }
}

