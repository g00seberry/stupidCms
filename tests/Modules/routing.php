<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Конфигурация тестов модуля Routing.
 * 
 * Покрывает резервирование путей и управление роутингом.
 */

uses(TestCase::class, RefreshDatabase::class)
    ->group('module:routing')
    ->in('../Feature/Domain/Routing')
    ->in('../Unit/Domain/Routing');

