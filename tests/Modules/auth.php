<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Конфигурация тестов модуля Auth.
 * 
 * Покрывает аутентификацию и авторизацию пользователей.
 */

uses(TestCase::class, RefreshDatabase::class)
    ->group('module:auth')
    ->in('../Feature/Api/Admin/V1/Auth')
    ->in('../Unit/Domain/Auth');

