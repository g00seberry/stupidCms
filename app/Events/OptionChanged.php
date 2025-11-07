<?php

namespace App\Events;

class OptionChanged
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $key,
        public readonly mixed $value,
        public readonly mixed $oldValue = null,
    ) {}
}

