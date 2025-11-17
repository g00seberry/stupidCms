<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Конфигурация тестов модуля Media.
 * 
 * Покрывает управление медиа-файлами, загрузку, обработку и валидацию.
 */

uses(TestCase::class, RefreshDatabase::class)
    ->group('module:media')
    ->in('../Feature/Api/Admin/V1/Media')
    ->in('../Feature/Api/Public/Media')
    ->in('../Feature/Domain/Media')
    ->in('../Unit/Domain/Media');

