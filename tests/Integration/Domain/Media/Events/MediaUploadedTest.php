<?php

declare(strict_types=1);

namespace Tests\Integration\Domain\Media\Events;

use App\Domain\Media\Events\MediaUploaded;
use App\Models\Media;
use Illuminate\Support\Facades\Event;
use Tests\Support\IntegrationTestCase;

/**
 * Тесты для события MediaUploaded.
 *
 * Проверяет, что событие корректно содержит модель Media и является сериализуемым.
 */
class MediaUploadedTest extends IntegrationTestCase
{
    

    /**
     * Тест: событие содержит модель Media.
     */
    public function test_event_contains_media_model(): void
    {
        $media = Media::factory()->create([
            'title' => 'Test Image',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'collection' => 'test',
        ]);

        $event = new MediaUploaded($media);

        $this->assertSame($media, $event->media);
        $this->assertInstanceOf(Media::class, $event->media);
        $this->assertEquals($media->id, $event->media->id);
        $this->assertEquals($media->title, $event->media->title);
    }

    /**
     * Тест: событие сериализуемо.
     */
    public function test_event_is_serializable(): void
    {
        $media = Media::factory()->create([
            'title' => 'Test Image',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);

        $event = new MediaUploaded($media);

        // Проверяем, что событие можно сериализовать
        $serialized = serialize($event);
        $this->assertIsString($serialized);

        // Проверяем, что событие можно десериализовать
        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(MediaUploaded::class, $unserialized);
        $this->assertEquals($event->media->id, $unserialized->media->id);
        $this->assertEquals($event->media->title, $unserialized->media->title);
    }

    /**
     * Тест: событие может быть отправлено через Event фасад.
     */
    public function test_event_can_be_dispatched(): void
    {
        Event::fake();

        $media = Media::factory()->create();
        $event = new MediaUploaded($media);

        Event::dispatch($event);

        Event::assertDispatched(MediaUploaded::class, function ($dispatchedEvent) use ($media) {
            return $dispatchedEvent->media->id === $media->id;
        });
    }
}



