<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Конфигурация тестов модуля Search.
 * 
 * Покрывает поиск и индексацию контента.
 */

uses(TestCase::class, RefreshDatabase::class)
    ->group('module:search')
    ->in('../Feature/Api/Admin/V1/Search')
    ->in('../Feature/Api/Public/Search')
    ->in('../Feature/Domain/Search')
    ->in('../Unit/Domain/Search');

