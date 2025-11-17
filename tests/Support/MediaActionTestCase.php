<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Media\Services\CollectionRulesResolver;
use App\Domain\Media\Services\ExifManager;
use App\Domain\Media\Services\MediaMetadataExtractor;
use App\Domain\Media\Services\StorageResolver;
use App\Domain\Media\Validation\MediaValidationPipeline;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\Concerns\ConfiguresMedia;

/**
 * Базовый класс для тестов Media Actions.
 *
 * Предоставляет:
 * - Готовые моки всех зависимостей Actions
 * - Автоматическую настройку Storage::fake()
 * - Автоматическую конфигурацию Media
 * - Методы для быстрой настройки успешных сценариев
 *
 * Используйте этот класс для Integration тестов Actions,
 * которые требуют моков сервисов, но работают с реальной БД.
 */
abstract class MediaActionTestCase extends IntegrationTestCase
{
    use ConfiguresMedia;

    protected MediaMetadataExtractor $metadataExtractor;
    protected StorageResolver $storageResolver;
    protected CollectionRulesResolver $collectionRulesResolver;
    protected MediaValidationPipeline $validationPipeline;
    protected ?ExifManager $exifManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configureMediaDefaults();
        Storage::fake('media');
        
        $this->setUpMediaMocks();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Инициализировать моки для Media сервисов.
     *
     * Создаёт моки всех зависимостей, используемых в MediaStoreAction
     * и других Media Actions.
     */
    protected function setUpMediaMocks(): void
    {
        $this->metadataExtractor = Mockery::mock(MediaMetadataExtractor::class);
        $this->storageResolver = Mockery::mock(StorageResolver::class);
        $this->collectionRulesResolver = Mockery::mock(CollectionRulesResolver::class);
        $this->validationPipeline = Mockery::mock(MediaValidationPipeline::class);
        $this->exifManager = null;
    }

    /**
     * Настроить стандартное поведение моков для успешного сценария загрузки.
     *
     * Этот метод настраивает моки так, чтобы:
     * - Валидация проходила успешно
     * - Правила коллекции были пустыми
     * - Storage resolver возвращал диск 'media'
     *
     * Вызовите этот метод в тестах, где нужен базовый успешный сценарий.
     */
    protected function mockSuccessfulUpload(): void
    {
        $this->validationPipeline->shouldReceive('validate')->byDefault();
        $this->collectionRulesResolver->shouldReceive('getRules')->andReturn([])->byDefault();
        $this->storageResolver->shouldReceive('resolveDiskName')->andReturn('media')->byDefault();
    }

    /**
     * Настроить мок для проваленной валидации.
     *
     * @param string $errorMessage Сообщение об ошибке валидации
     */
    protected function mockFailedValidation(string $errorMessage = 'Validation failed'): void
    {
        $this->validationPipeline
            ->shouldReceive('validate')
            ->andThrow(new \RuntimeException($errorMessage));
    }

    /**
     * Настроить мок для специфичных правил коллекции.
     *
     * @param array<string, mixed> $rules Правила для коллекции
     */
    protected function mockCollectionRules(array $rules): void
    {
        $this->collectionRulesResolver
            ->shouldReceive('getRules')
            ->andReturn($rules);
    }

    /**
     * Настроить мок для специфичного диска.
     *
     * @param string $diskName Имя диска
     */
    protected function mockStorageDisk(string $diskName): void
    {
        $this->storageResolver
            ->shouldReceive('resolveDiskName')
            ->andReturn($diskName);
    }
}

