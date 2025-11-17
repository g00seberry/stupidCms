<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Конфигурация тестов модуля Entries.
 * 
 * Покрывает управление записями контента.
 */

uses(TestCase::class, RefreshDatabase::class)
    ->group('module:entries')
    ->in('../Feature/Api/Admin/V1/Entries')
    ->in('../Feature/Domain/Entries')
    ->in('../Unit/Domain/Entries');

