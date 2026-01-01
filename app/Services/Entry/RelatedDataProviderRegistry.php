<?php

declare(strict_types=1);

namespace App\Services\Entry;

/**
 * Регистр провайдеров связанных данных.
 *
 * Управляет регистрацией и получением провайдеров связанных данных
 * для расширяемости системы.
 *
 * @package App\Services\Entry
 */
class RelatedDataProviderRegistry
{
    /**
     * @var array<RelatedDataProviderInterface> Зарегистрированные провайдеры
     */
    private array $providers = [];

    /**
     * Зарегистрировать провайдер связанных данных.
     *
     * @param RelatedDataProviderInterface $provider Провайдер для регистрации
     * @return void
     */
    public function register(RelatedDataProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Получить все зарегистрированные провайдеры.
     *
     * @return array<RelatedDataProviderInterface> Массив провайдеров
     */
    public function getAllProviders(): array
    {
        return $this->providers;
    }

    /**
     * Получить провайдер по ключу.
     *
     * @param string $key Ключ провайдера (например, 'entryData')
     * @return RelatedDataProviderInterface|null Провайдер или null, если не найден
     */
    public function getProviderByKey(string $key): ?RelatedDataProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->getKey() === $key) {
                return $provider;
            }
        }

        return null;
    }
}

