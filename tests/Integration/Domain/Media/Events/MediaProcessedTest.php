<?php

declare(strict_types=1);

namespace Tests\Integration\Domain\Media\Events;

use App\Domain\Media\Events\MediaProcessed;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Event;
use Tests\Support\IntegrationTestCase;

/**
 * Тесты для события MediaProcessed.
 *
 * Проверяет, что событие корректно содержит модели Media и MediaVariant и является сериализуемым.
 */
class MediaProcessedTest extends IntegrationTestCase
{
    

    /**
     * Тест: событие содержит Media и MediaVariant.
     */
    public function test_event_contains_media_and_variant(): void
    {
        $media = Media::factory()->create([
            'title' => 'Test Image',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'collection' => 'test',
        ]);

        $variant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
            'size_bytes' => 512,
            'width' => 150,
            'height' => 150,
        ]);

        $event = new MediaProcessed($media, $variant);

        $this->assertSame($media, $event->media);
        $this->assertInstanceOf(Media::class, $event->media);
        $this->assertEquals($media->id, $event->media->id);

        $this->assertSame($variant, $event->variant);
        $this->assertInstanceOf(MediaVariant::class, $event->variant);
        $this->assertEquals($variant->id, $event->variant->id);
        $this->assertEquals($variant->variant, $event->variant->variant);
        $this->assertEquals($media->id, $event->variant->media_id);
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

        $variant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
            'size_bytes' => 512,
        ]);

        $event = new MediaProcessed($media, $variant);

        // Проверяем, что событие можно сериализовать
        $serialized = serialize($event);
        $this->assertIsString($serialized);

        // Проверяем, что событие можно десериализовать
        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(MediaProcessed::class, $unserialized);
        $this->assertEquals($event->media->id, $unserialized->media->id);
        $this->assertEquals($event->variant->id, $unserialized->variant->id);
        $this->assertEquals($event->variant->variant, $unserialized->variant->variant);
    }

    /**
     * Тест: событие может быть отправлено через Event фасад.
     */
    public function test_event_can_be_dispatched(): void
    {
        Event::fake();

        $media = Media::factory()->create();
        $variant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'preview',
        ]);

        $event = new MediaProcessed($media, $variant);

        Event::dispatch($event);

        Event::assertDispatched(MediaProcessed::class, function ($dispatchedEvent) use ($media, $variant) {
            return $dispatchedEvent->media->id === $media->id
                && $dispatchedEvent->variant->id === $variant->id;
        });
    }
}



