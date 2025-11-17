<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Конфигурация тестов модуля Plugins.
 * 
 * Покрывает систему плагинов, активацию, деактивацию и управление.
 */

uses(TestCase::class, RefreshDatabase::class)
    ->group('module:plugins')
    ->in('../Feature/Api/Admin/V1/Plugins')
    ->in('../Feature/Domain/Plugins')
    ->in('../Unit/Domain/Plugins');

