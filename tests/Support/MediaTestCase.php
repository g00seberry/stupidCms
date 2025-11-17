<?php

declare(strict_types=1);

namespace Tests\Support;

use Tests\Support\Concerns\ConfiguresMedia;
use Tests\Support\Concerns\UsesFakeStorage;

/**
 * Базовый класс для тестов Media.
 *
 * Включает:
 * - Все возможности FeatureTestCase (RefreshDatabase, HasAdminUser)
 * - Автоматическую настройку конфигурации Media
 * - Автоматическую настройку фейкового хранилища
 */
abstract class MediaTestCase extends FeatureTestCase
{
    use ConfiguresMedia;
    use UsesFakeStorage;

    /**
     * Настройка перед каждым тестом.
     *
     * Автоматически настраивает конфигурацию Media и фейковое хранилище.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configureMediaDefaults();
        $this->setUpFakeStorage('media');
    }
}

