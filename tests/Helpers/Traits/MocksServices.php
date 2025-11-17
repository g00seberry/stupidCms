<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use Mockery;
use Mockery\MockInterface;

/**
 * Трейт для упрощения создания моков сервисов в тестах.
 */
trait MocksServices
{
    /**
     * Создать и зарегистрировать мок сервиса в контейнере.
     *
     * @template T
     * @param class-string<T> $abstract Класс или интерфейс для мока
     * @param callable|null $mock Опциональная функция для настройки мока
     * @return MockInterface&T
     */
    protected function mockService(string $abstract, ?callable $mock = null): MockInterface
    {
        $mockInstance = Mockery::mock($abstract);

        if ($mock !== null) {
            $mock($mockInstance);
        }

        $this->app->instance($abstract, $mockInstance);

        return $mockInstance;
    }

    /**
     * Создать частичный мок сервиса (spy).
     *
     * @template T
     * @param class-string<T> $abstract Класс для мока
     * @return MockInterface&T
     */
    protected function spyService(string $abstract): MockInterface
    {
        $spy = Mockery::spy($abstract);
        $this->app->instance($abstract, $spy);

        return $spy;
    }
}

