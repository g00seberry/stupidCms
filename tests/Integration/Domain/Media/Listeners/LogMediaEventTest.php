<?php

declare(strict_types=1);

namespace Tests\Integration\Domain\Media\Listeners;

use App\Domain\Media\Events\MediaDeleted;
use App\Domain\Media\Events\MediaProcessed;
use App\Domain\Media\Events\MediaUploaded;
use App\Domain\Media\Listeners\LogMediaEvent;
use App\Models\Media;
use App\Models\MediaVariant;
use Tests\Support\IntegrationTestCase;

/**
 * Тесты для слушателя LogMediaEvent.
 *
 * Проверяет, что слушатель корректно обрабатывает события медиа-файлов без ошибок.
 */
class LogMediaEventTest extends IntegrationTestCase
{
    

    /**
     * Тест: обработка события MediaUploaded без ошибок.
     */
    public function test_it_handles_media_uploaded_event(): void
    {
        $listener = new LogMediaEvent();
        $media = Media::factory()->create([
            'title' => 'Test Image',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'collection' => 'test',
        ]);

        $event = new MediaUploaded($media);
        
        // Проверяем, что метод выполняется без ошибок
        $listener->handleMediaUploaded($event);
        
        $this->assertTrue(true); // Метод выполнен успешно
    }

    /**
     * Тест: обработка события MediaProcessed без ошибок.
     */
    public function test_it_handles_media_processed_event(): void
    {
        $listener = new LogMediaEvent();
        $media = Media::factory()->create();
        $variant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
            'size_bytes' => 512,
            'width' => 150,
            'height' => 150,
        ]);

        $event = new MediaProcessed($media, $variant);
        
        // Проверяем, что метод выполняется без ошибок
        $listener->handleMediaProcessed($event);
        
        $this->assertTrue(true); // Метод выполнен успешно
    }

    /**
     * Тест: обработка события MediaDeleted без ошибок.
     */
    public function test_it_handles_media_deleted_event(): void
    {
        $listener = new LogMediaEvent();
        $media = Media::factory()->create([
            'title' => 'Test Image',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);

        $event = new MediaDeleted($media);
        
        // Проверяем, что метод выполняется без ошибок
        $listener->handleMediaDeleted($event);
        
        $this->assertTrue(true); // Метод выполнен успешно
    }
}



