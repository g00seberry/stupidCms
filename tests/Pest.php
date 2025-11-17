<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Базовая конфигурация Pest для всех тестов.
 */

// Feature-тесты с БД
uses(TestCase::class, RefreshDatabase::class)
    ->in('Feature');

// Unit-тесты без БД
uses(TestCase::class)
    ->in('Unit');

// Загрузка модульных конфигураций
$modules = glob(__DIR__ . '/Modules/*.php');
if ($modules !== false) {
    foreach ($modules as $module) {
        require $module;
    }
}

