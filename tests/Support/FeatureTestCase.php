<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Concerns\HasAdminUser;

/**
 * Базовый класс для Feature тестов.
 *
 * Включает:
 * - RefreshDatabase для полной пересборки БД между тестами
 * - HasAdminUser для создания административных пользователей
 */
abstract class FeatureTestCase extends BaseTestCase
{
    use RefreshDatabase;
    use HasAdminUser;
}

