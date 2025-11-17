<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Конфигурация тестов модуля View.
 * 
 * Покрывает рендеринг шаблонов и представлений.
 */

uses(TestCase::class, RefreshDatabase::class)
    ->group('module:view')
    ->in('../Unit/Domain/View');

