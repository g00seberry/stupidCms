<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Базовый класс для Integration тестов.
 *
 * Integration тесты - это тесты доменной логики с использованием БД,
 * но без HTTP слоя (контроллеров, middleware).
 *
 * Включает:
 * - RefreshDatabase для полной изоляции между тестами
 * - Методы для работы с доменной логикой
 *
 * Когда использовать:
 * - Тестирование Actions с реальной БД
 * - Тестирование Repositories с реальной БД
 * - Тестирование Services, которые взаимодействуют с БД
 *
 * Когда НЕ использовать:
 * - Unit тесты (без БД или с моками) - используйте TestCase
 * - Feature тесты (HTTP) - используйте FeatureTestCase
 *
 * Примечание: используется RefreshDatabase для автоматического управления миграциями.
 */
abstract class IntegrationTestCase extends BaseTestCase
{
    use RefreshDatabase;
}

