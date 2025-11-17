<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Базовый класс для всех тестов проекта.
 * 
 * Предоставляет общую инфраструктуру для Unit и Feature тестов.
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}

