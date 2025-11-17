<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Конфигурация тестов модуля Options.
 * 
 * Покрывает управление опциями системы.
 */

uses(TestCase::class, RefreshDatabase::class)
    ->group('module:options')
    ->in('../Feature/Api/Admin/V1/Options')
    ->in('../Unit/Domain/Options');

