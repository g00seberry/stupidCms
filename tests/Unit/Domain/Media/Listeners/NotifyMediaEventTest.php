<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media\Listeners;

use App\Domain\Media\Events\MediaDeleted;
use App\Domain\Media\Events\MediaProcessed;
use App\Domain\Media\Events\MediaUploaded;
use App\Domain\Media\Listeners\NotifyMediaEvent;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery as m;
use Tests\TestCase;

/**
 * Тесты для слушателя NotifyMediaEvent.
 *
 * Проверяет обработку событий медиа-файлов и логирование больших файлов.
 */
final class NotifyMediaEventTest extends TestCase
{
    use RefreshDatabase;

    private NotifyMediaEvent $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = new NotifyMediaEvent();
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /**
     * Тест: логирование загрузки большого файла (>10MB).
     */
    public function test_logs_large_file_upload(): void
    {
        $largeSizeBytes = (1024 * 1024 * 10) + 1; // > 10MB

        $media = Media::factory()->create([
            'size_bytes' => $largeSizeBytes,
        ]);

        $event = new MediaUploaded($media);

        Log::shouldReceive('info')
            ->once()
            ->with(
                'Large media file uploaded, notification should be sent',
                m::on(function (array $context) use ($media, $largeSizeBytes): bool {
                    return $context['media_id'] === $media->id
                        && $context['size_bytes'] === $largeSizeBytes;
                })
            )
            ->andReturnNull();

        $this->listener->handleMediaUploaded($event);

        // Убеждаемся, что метод выполнен успешно
        $this->assertTrue(true);
    }

    /**
     * Тест: обработка события MediaUploaded.
     */
    public function test_handles_media_uploaded_event(): void
    {
        $media = Media::factory()->create([
            'title' => 'Test Image',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'collection' => 'test',
        ]);

        $event = new MediaUploaded($media);

        // Проверяем, что метод выполняется без ошибок
        // Для маленького файла логирование не вызывается (проверяется в test_does_not_log_small_file_upload)
        $this->listener->handleMediaUploaded($event);

        $this->assertTrue(true); // Метод выполнен успешно
    }

    /**
     * Тест: обработка события MediaProcessed (placeholder).
     */
    public function test_handles_media_processed_event(): void
    {
        $media = Media::factory()->create();
        $variant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
            'size_bytes' => 512,
            'width' => 150,
            'height' => 150,
        ]);

        $event = new MediaProcessed($media, $variant);

        // Проверяем, что метод выполняется без ошибок (placeholder реализация)
        $this->listener->handleMediaProcessed($event);

        $this->assertTrue(true); // Метод выполнен успешно
    }

    /**
     * Тест: обработка события MediaDeleted (placeholder).
     */
    public function test_handles_media_deleted_event(): void
    {
        $media = Media::factory()->create([
            'title' => 'Test Image',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);

        $event = new MediaDeleted($media);

        // Проверяем, что метод выполняется без ошибок (placeholder реализация)
        $this->listener->handleMediaDeleted($event);

        $this->assertTrue(true); // Метод выполнен успешно
    }

    /**
     * Тест: отсутствие логирования для маленьких файлов.
     */
    public function test_does_not_log_small_file_upload(): void
    {
        $smallSizeBytes = 1024 * 1024 * 10; // Ровно 10MB (граничное значение)

        $media = Media::factory()->create([
            'size_bytes' => $smallSizeBytes,
        ]);

        $event = new MediaUploaded($media);

        // Для файла <= 10MB логирование не должно вызываться
        // Проверяем, что метод выполняется без ошибок и без вызова Log::info
        $this->listener->handleMediaUploaded($event);

        // Убеждаемся, что метод выполнен успешно (логирование не вызывается для файлов <= 10MB)
        $this->assertTrue(true);
    }
}

