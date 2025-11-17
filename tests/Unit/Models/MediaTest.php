<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Media;
use App\Models\MediaMetadata;
use App\Models\MediaVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Тесты для модели Media.
 */
final class MediaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * kind возвращает 'image' для image/* MIME.
     */
    public function test_kind_returns_image_for_image_mime(): void
    {
        $media = Media::factory()->create([
            'mime' => 'image/jpeg',
        ]);

        $this->assertSame('image', $media->kind());
    }

    /**
     * kind возвращает 'video' для video/* MIME.
     */
    public function test_kind_returns_video_for_video_mime(): void
    {
        $media = Media::factory()->create([
            'mime' => 'video/mp4',
        ]);

        $this->assertSame('video', $media->kind());
    }

    /**
     * kind возвращает 'audio' для audio/* MIME.
     */
    public function test_kind_returns_audio_for_audio_mime(): void
    {
        $media = Media::factory()->create([
            'mime' => 'audio/mpeg',
        ]);

        $this->assertSame('audio', $media->kind());
    }

    /**
     * kind возвращает 'document' для других MIME.
     */
    public function test_kind_returns_document_for_other_mime(): void
    {
        $media = Media::factory()->create([
            'mime' => 'application/pdf',
        ]);

        $this->assertSame('document', $media->kind());
    }

    /**
     * Проверка уникального ограничения на (disk, path).
     */
    public function test_has_unique_constraint_on_disk_and_path(): void
    {
        $disk = 'media';
        $path = '2025/01/17/test.jpg';

        Media::factory()->create([
            'disk' => $disk,
            'path' => $path,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Media::factory()->create([
            'disk' => $disk,
            'path' => $path,
        ]);
    }

    /**
     * Мягкое удаление медиа.
     */
    public function test_soft_deletes_media(): void
    {
        $media = Media::factory()->create();

        $media->delete();

        $this->assertSoftDeleted('media', [
            'id' => $media->id,
        ]);
        $this->assertNotNull($media->fresh()->deleted_at);
    }

    /**
     * Восстановление мягко удалённого медиа.
     */
    public function test_restores_soft_deleted_media(): void
    {
        $media = Media::factory()->create();
        $media->delete();

        $this->assertSoftDeleted('media', [
            'id' => $media->id,
        ]);

        $media->restore();

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'deleted_at' => null,
        ]);
        $this->assertNull($media->fresh()->deleted_at);
    }

    /**
     * Использование ULID в качестве первичного ключа.
     */
    public function test_uses_ulid_as_primary_key(): void
    {
        $media = Media::factory()->create();

        $this->assertIsString($media->id);
        $this->assertTrue(Str::isUlid($media->id));
        $this->assertSame(26, strlen($media->id));
    }

    /**
     * Наличие отношения variants.
     */
    public function test_has_variants_relationship(): void
    {
        $media = Media::factory()->create();
        $variant1 = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
        ]);
        $variant2 = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'medium',
        ]);

        $variants = $media->variants;

        $this->assertCount(2, $variants);
        $this->assertTrue($variants->contains($variant1));
        $this->assertTrue($variants->contains($variant2));
    }

    /**
     * Наличие отношения metadata.
     */
    public function test_has_metadata_relationship(): void
    {
        $media = Media::factory()->create();
        $metadata = MediaMetadata::factory()->create([
            'media_id' => $media->id,
        ]);

        $this->assertInstanceOf(MediaMetadata::class, $media->metadata);
        $this->assertSame($metadata->id, $media->metadata->id);
    }
}

