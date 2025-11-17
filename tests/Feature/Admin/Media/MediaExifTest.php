<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Domain\Media\Actions\MediaStoreAction;
use App\Domain\Media\DTO\MediaMetadataDTO;
use App\Domain\Media\Services\CollectionRulesResolver;
use App\Domain\Media\Services\ExifManager;
use App\Domain\Media\Services\MediaMetadataExtractor;
use App\Domain\Media\Services\StorageResolver;
use App\Domain\Media\Validation\MediaValidationPipeline;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\Support\MediaTestCase;

final class MediaExifTest extends MediaTestCase
{
    private MediaMetadataExtractor $metadataExtractor;
    private StorageResolver $storageResolver;
    private CollectionRulesResolver $collectionRulesResolver;
    private MediaValidationPipeline $validationPipeline;
    private ExifManager $exifManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metadataExtractor = Mockery::mock(MediaMetadataExtractor::class);
        $this->storageResolver = Mockery::mock(StorageResolver::class);
        $this->collectionRulesResolver = Mockery::mock(CollectionRulesResolver::class);
        $this->validationPipeline = Mockery::mock(MediaValidationPipeline::class);
        $this->exifManager = Mockery::mock(ExifManager::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createAction(): MediaStoreAction
    {
        return new MediaStoreAction(
            $this->metadataExtractor,
            $this->storageResolver,
            $this->collectionRulesResolver,
            $this->validationPipeline,
            $this->exifManager
        );
    }

    /**
     * Тест: сохранение EXIF при пустом whitelist.
     */
    public function test_preserves_exif_when_whitelist_empty(): void
    {
        config()->set('media.exif.whitelist', []);
        config()->set('media.exif.strip', false);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $exif = [
            'IFD0' => [
                'Make' => 'Canon',
                'Model' => 'EOS 5D',
                'DateTime' => '2024:01:01 12:00:00',
            ],
            'EXIF' => [
                'ExposureTime' => '1/125',
                'FNumber' => 'f/2.8',
            ],
        ];

        $this->validationPipeline->shouldReceive('validate')->once();
        $this->collectionRulesResolver->shouldReceive('getRules')->once()->andReturn([]);
        $this->storageResolver->shouldReceive('resolveDiskName')->once()->andReturn('media');

        $metadata = new MediaMetadataDTO(width: 100, height: 100, exif: $exif);
        $this->metadataExtractor->shouldReceive('extract')->once()->andReturn($metadata);

        // ExifManager не должен вызываться для фильтрации, если whitelist пуст
        $this->exifManager->shouldNotReceive('filterExif');

        $action = $this->createAction();
        $media = $action->execute($file);

        $this->assertNotNull($media->exif_json);
        $this->assertSame($exif, $media->exif_json);
    }

    /**
     * Тест: фильтрация EXIF по whitelist.
     */
    public function test_filters_exif_by_whitelist(): void
    {
        config()->set('media.exif.whitelist', ['IFD0.Make', 'IFD0.Model']);
        config()->set('media.exif.strip', false);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $exif = [
            'IFD0' => [
                'Make' => 'Canon',
                'Model' => 'EOS 5D',
                'DateTime' => '2024:01:01 12:00:00',
            ],
            'EXIF' => [
                'ExposureTime' => '1/125',
            ],
        ];

        $filteredExif = [
            'IFD0' => [
                'Make' => 'Canon',
                'Model' => 'EOS 5D',
            ],
        ];

        $this->validationPipeline->shouldReceive('validate')->once();
        $this->collectionRulesResolver->shouldReceive('getRules')->once()->andReturn([]);
        $this->storageResolver->shouldReceive('resolveDiskName')->once()->andReturn('media');

        $metadata = new MediaMetadataDTO(width: 100, height: 100, exif: $exif);
        $this->metadataExtractor->shouldReceive('extract')->once()->andReturn($metadata);

        $this->exifManager->shouldReceive('filterExif')
            ->once()
            ->with($exif, ['IFD0.Make', 'IFD0.Model'])
            ->andReturn($filteredExif);

        $action = $this->createAction();
        $media = $action->execute($file);

        $this->assertNotNull($media->exif_json);
        $this->assertSame($filteredExif, $media->exif_json);
        $this->assertArrayNotHasKey('DateTime', $media->exif_json['IFD0'] ?? []);
        $this->assertArrayNotHasKey('EXIF', $media->exif_json);
    }

    /**
     * Тест: удаление EXIF при соответствующей настройке.
     */
    public function test_strips_exif_when_configured(): void
    {
        config()->set('media.exif.strip', true);
        config()->set('media.exif.whitelist', []);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $exif = [
            'IFD0' => [
                'Make' => 'Canon',
                'Model' => 'EOS 5D',
            ],
        ];

        $this->validationPipeline->shouldReceive('validate')->once();
        $this->collectionRulesResolver->shouldReceive('getRules')->once()->andReturn([]);
        $this->storageResolver->shouldReceive('resolveDiskName')->once()->andReturn('media');

        $metadata = new MediaMetadataDTO(width: 100, height: 100, exif: $exif);
        $this->metadataExtractor->shouldReceive('extract')->once()->andReturn($metadata);

        // ExifManager не должен вызываться для фильтрации, если strip включен
        $this->exifManager->shouldNotReceive('filterExif');

        $action = $this->createAction();
        $media = $action->execute($file);

        // EXIF должен быть удалён
        $this->assertNull($media->exif_json);
    }

    /**
     * Тест: обработка некорректных EXIF данных.
     */
    public function test_handles_malformed_exif_data(): void
    {
        config()->set('media.exif.whitelist', []);
        config()->set('media.exif.strip', false);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        // Некорректные EXIF данные (неправильная структура)
        $malformedExif = [
            'IFD0' => 'invalid', // Должно быть массивом
            'EXIF' => null,
        ];

        $this->validationPipeline->shouldReceive('validate')->once();
        $this->collectionRulesResolver->shouldReceive('getRules')->once()->andReturn([]);
        $this->storageResolver->shouldReceive('resolveDiskName')->once()->andReturn('media');

        $metadata = new MediaMetadataDTO(width: 100, height: 100, exif: $malformedExif);
        $this->metadataExtractor->shouldReceive('extract')->once()->andReturn($metadata);

        // ExifManager должен корректно обработать некорректные данные
        $this->exifManager->shouldNotReceive('filterExif');

        $action = $this->createAction();
        $media = $action->execute($file);

        // Система должна сохранить данные как есть (или обработать ошибку)
        // В текущей реализации данные сохраняются как есть
        $this->assertNotNull($media->exif_json);
    }

    /**
     * Тест: извлечение Orientation из EXIF.
     */
    public function test_extracts_orientation_from_exif(): void
    {
        config()->set('media.exif.whitelist', []);
        config()->set('media.exif.strip', false);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $exif = [
            'IFD0' => [
                'Orientation' => 6, // Поворот на 90° по часовой стрелке
            ],
            'EXIF' => [
                'Orientation' => 6,
            ],
        ];

        $this->validationPipeline->shouldReceive('validate')->once();
        $this->collectionRulesResolver->shouldReceive('getRules')->once()->andReturn([]);
        $this->storageResolver->shouldReceive('resolveDiskName')->once()->andReturn('media');

        $metadata = new MediaMetadataDTO(width: 100, height: 100, exif: $exif);
        $this->metadataExtractor->shouldReceive('extract')->once()->andReturn($metadata);

        $this->exifManager->shouldNotReceive('filterExif');

        $action = $this->createAction();
        $media = $action->execute($file);

        $this->assertNotNull($media->exif_json);
        $this->assertArrayHasKey('IFD0', $media->exif_json);
        $this->assertArrayHasKey('Orientation', $media->exif_json['IFD0']);
        $this->assertSame(6, $media->exif_json['IFD0']['Orientation']);
    }
}

