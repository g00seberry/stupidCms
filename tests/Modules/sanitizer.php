<?php

declare(strict_types=1);

use Tests\TestCase;

/**
 * Конфигурация тестов модуля Sanitizer.
 * 
 * Покрывает санитизацию контента.
 */

uses(TestCase::class)
    ->group('module:sanitizer')
    ->in('../Unit/Domain/Sanitizer');

