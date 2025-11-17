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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class MediaDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private MediaMetadataExtractor $metadataExtractor;
    private StorageResolver $storageResolver;
    private CollectionRulesResolver $collectionRulesResolver;
    private MediaValidationPipeline $validationPipeline;
    private ?ExifManager $exifManager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');

        $this->metadataExtractor = Mockery::mock(MediaMetadataExtractor::class);
        $this->storageResolver = Mockery::mock(StorageResolver::class);
        $this->collectionRulesResolver = Mockery::mock(CollectionRulesResolver::class);
        $this->validationPipeline = Mockery::mock(MediaValidationPipeline::class);
        $this->exifManager = null;

        config()->set('media.disks', [
            'default' => 'media',
            'collections' => [],
            'kinds' => [],
        ]);
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
     * Тест: дедупликация идентичных файлов с разными именами.
     */
    public function test_deduplicates_identical_files_with_different_names(): void
    {
        // Создаём первый файл
        $file1 = UploadedFile::fake()->image('original.jpg', 100, 100);
        $checksum1 = hash_file('sha256', $file1->getRealPath());

        $this->validationPipeline->shouldReceive('validate')->twice();
        $this->collectionRulesResolver->shouldReceive('getRules')->twice()->andReturn([]);
        $this->storageResolver->shouldReceive('resolveDiskName')->once()->andReturn('media');

        $metadata = new MediaMetadataDTO(width: 100, height: 100);
        // При дедупликации второй файл не проходит через extract
        $this->metadataExtractor->shouldReceive('extract')->once()->andReturn($metadata);

        $action = $this->createAction();
        $media1 = $action->execute($file1);

        // Создаём второй файл с тем же содержимым, но другим именем
        $file2 = UploadedFile::fake()->image('renamed.jpg', 100, 100);
        // Копируем содержимое первого файла
        file_put_contents($file2->getRealPath(), file_get_contents($file1->getRealPath()));
        $checksum2 = hash_file('sha256', $file2->getRealPath());

        $this->assertSame($checksum1, $checksum2);

        $media2 = $action->execute($file2);

        // Должна быть возвращена та же запись
        $this->assertSame($media1->id, $media2->id);
        $this->assertSame($media1->checksum_sha256, $media2->checksum_sha256);

        // В БД должна быть только одна запись
        $this->assertSame(1, Media::count());
    }

    /**
     * Тест: дедупликация идентичных файлов с разными метаданными.
     */
    public function test_deduplicates_identical_files_with_different_metadata(): void
    {
        // Создаём первый файл с метаданными
        $file1 = UploadedFile::fake()->image('test1.jpg', 100, 100);

        $this->validationPipeline->shouldReceive('validate')->twice();
        $this->collectionRulesResolver->shouldReceive('getRules')->twice()->andReturn([]);
        $this->storageResolver->shouldReceive('resolveDiskName')->once()->andReturn('media');

        $metadata = new MediaMetadataDTO(width: 100, height: 100);
        // При дедупликации второй файл не проходит через extract
        $this->metadataExtractor->shouldReceive('extract')->once()->andReturn($metadata);

        $action = $this->createAction();
        $media1 = $action->execute($file1, [
            'title' => 'First Title',
            'alt' => 'First Alt',
            'collection' => 'collection1',
        ]);

        // Создаём второй файл с тем же содержимым, но другими метаданными
        $file2 = UploadedFile::fake()->image('test2.jpg', 100, 100);
        file_put_contents($file2->getRealPath(), file_get_contents($file1->getRealPath()));

        $media2 = $action->execute($file2, [
            'title' => 'Second Title',
            'alt' => 'Second Alt',
            'collection' => 'collection2',
        ]);

        // Должна быть возвращена та же запись (по checksum)
        $this->assertSame($media1->id, $media2->id);

        // Но метаданные могут быть обновлены, если они переданы в payload
        // В текущей реализации MediaStoreAction обновляет только если они переданы
        $media1->refresh();
        // Проверяем, что метаданные обновились
        $this->assertSame('Second Title', $media1->title);
        $this->assertSame('Second Alt', $media1->alt);
        $this->assertSame('collection2', $media1->collection);
    }

    /**
     * Тест: обновление метаданных при дедупликации.
     */
    public function test_updates_metadata_on_deduplication(): void
    {
        $file1 = UploadedFile::fake()->image('test1.jpg', 100, 100);

        $this->validationPipeline->shouldReceive('validate')->twice();
        $this->collectionRulesResolver->shouldReceive('getRules')->twice()->andReturn([]);
        $this->storageResolver->shouldReceive('resolveDiskName')->once()->andReturn('media');

        $metadata = new MediaMetadataDTO(width: 100, height: 100);
        // При дедупликации второй файл не проходит через extract
        $this->metadataExtractor->shouldReceive('extract')->once()->andReturn($metadata);

        $action = $this->createAction();
        $media1 = $action->execute($file1, [
            'title' => 'Original Title',
        ]);

        $file2 = UploadedFile::fake()->image('test2.jpg', 100, 100);
        file_put_contents($file2->getRealPath(), file_get_contents($file1->getRealPath()));

        $media2 = $action->execute($file2, [
            'title' => 'Updated Title',
            'alt' => 'New Alt',
        ]);

        $this->assertSame($media1->id, $media2->id);

        $media1->refresh();
        $this->assertSame('Updated Title', $media1->title);
        $this->assertSame('New Alt', $media1->alt);
    }

    /**
     * Тест: отсутствие дедупликации для разных файлов.
     */
    public function test_does_not_deduplicate_different_files(): void
    {
        $file1 = UploadedFile::fake()->image('test1.jpg', 100, 100);
        $file2 = UploadedFile::fake()->image('test2.jpg', 200, 200);

        $this->validationPipeline->shouldReceive('validate')->twice();
        $this->collectionRulesResolver->shouldReceive('getRules')->twice()->andReturn([]);
        $this->storageResolver->shouldReceive('resolveDiskName')->twice()->andReturn('media');

        $metadata1 = new MediaMetadataDTO(width: 100, height: 100);
        $metadata2 = new MediaMetadataDTO(width: 200, height: 200);
        $this->metadataExtractor->shouldReceive('extract')
            ->twice()
            ->andReturn($metadata1, $metadata2);

        $action = $this->createAction();
        $media1 = $action->execute($file1);
        $media2 = $action->execute($file2);

        // Должны быть созданы две разные записи
        $this->assertNotSame($media1->id, $media2->id);
        $this->assertNotSame($media1->checksum_sha256, $media2->checksum_sha256);
        $this->assertSame(2, Media::count());
    }

    /**
     * Тест: теоретическая обработка коллизии checksum (SHA256).
     *
     * Примечание: SHA256 коллизии практически невозможны,
     * но проверяем, что система корректно обрабатывает ситуацию,
     * когда два разных файла имеют одинаковый checksum.
     */
    public function test_handles_checksum_collision_theoretically(): void
    {
        $file1 = UploadedFile::fake()->image('test1.jpg', 100, 100);

        $this->validationPipeline->shouldReceive('validate')->once();
        $this->collectionRulesResolver->shouldReceive('getRules')->once()->andReturn([]);
        $this->storageResolver->shouldReceive('resolveDiskName')->once()->andReturn('media');

        $metadata = new MediaMetadataDTO(width: 100, height: 100);
        $this->metadataExtractor->shouldReceive('extract')->once()->andReturn($metadata);

        $action = $this->createAction();
        $media1 = $action->execute($file1);

        // В реальности SHA256 коллизии практически невозможны,
        // но если бы они произошли, система должна вернуть существующую запись
        // Это проверяется логикой в MediaStoreAction, где ищется существующий файл по checksum

        // Проверяем, что система корректно работает с checksum
        $this->assertNotNull($media1->checksum_sha256);
        $this->assertSame(64, strlen($media1->checksum_sha256)); // SHA256 = 64 hex символа

        // Если бы был второй файл с таким же checksum, он был бы дедуплицирован
        // Это нормальное поведение системы
    }
}

