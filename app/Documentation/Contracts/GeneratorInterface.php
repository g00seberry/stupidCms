<?php

declare(strict_types=1);

namespace App\Documentation\Contracts;

use App\Documentation\DocEntity;

interface GeneratorInterface
{
    /**
     * Генерирует документацию из массива сущностей.
     *
     * @param array<DocEntity> $entities
     */
    public function generate(array $entities): void;
}

