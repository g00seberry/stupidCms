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

// Unit-тесты без БД (исключая Domain/Search, которые не требуют TestCase)
uses(TestCase::class)
    ->in('Unit/Auth')
    ->in('Unit/Domain/Media')
    ->in('Unit/Domain/Blueprint/Validation')
    ->in('Unit/Entries')
    ->in('Unit/Helpers')
    ->in('Unit/Media')
    ->in('Unit/Models')
    ->in('Unit/PostTypes')
    ->in('Unit/Routing')
    ->in('Unit/Rules')
    ->in('Unit/Support');

// Unit-тесты с БД для Services/Blueprint
uses(TestCase::class, RefreshDatabase::class)
    ->in('Unit/Services/Blueprint');

// Unit-тесты с БД для Services/Entry
uses(TestCase::class, RefreshDatabase::class)
    ->in('Unit/Services/Entry');

// Unit-тесты с БД для Listeners/Blueprint
uses(TestCase::class, RefreshDatabase::class)
    ->in('Unit/Listeners/Blueprint');

// Загрузка модульных конфигураций
$modules = glob(__DIR__ . '/Modules/*.php');
if ($modules !== false) {
    foreach ($modules as $module) {
        require $module;
    }
}

