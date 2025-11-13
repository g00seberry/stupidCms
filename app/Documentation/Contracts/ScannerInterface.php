<?php

declare(strict_types=1);

namespace App\Documentation\Contracts;

use App\Documentation\DocEntity;

interface ScannerInterface
{
    /**
     * Сканирует код и возвращает массив DocEntity.
     *
     * @return array<DocEntity>
     */
    public function scan(): array;
}

