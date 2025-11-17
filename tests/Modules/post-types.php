<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Конфигурация тестов модуля PostTypes.
 * 
 * Покрывает управление типами записей.
 */

uses(TestCase::class, RefreshDatabase::class)
    ->group('module:post-types')
    ->in('../Feature/Api/Admin/V1/PostTypes')
    ->in('../Unit/Domain/PostTypes');

