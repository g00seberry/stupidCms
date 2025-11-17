<?php

declare(strict_types=1);

namespace Tests\Integration\Domain\Media\Actions;

use App\Domain\Media\Actions\UpdateMediaMetadataAction;
use App\Models\Media;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Support\IntegrationTestCase;

final class UpdateMediaMetadataActionTest extends IntegrationTestCase
{
    

    private UpdateMediaMetadataAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateMediaMetadataAction();
    }

    public function test_updates_title_alt_collection(): void
    {
        $media = Media::factory()->create([
            'title' => 'Old Title',
            'alt' => 'Old Alt',
            'collection' => 'old-collection',
        ]);

        $updated = $this->action->execute($media->id, [
            'title' => 'New Title',
            'alt' => 'New Alt',
            'collection' => 'new-collection',
        ]);

        $this->assertSame('New Title', $updated->title);
        $this->assertSame('New Alt', $updated->alt);
        $this->assertSame('new-collection', $updated->collection);

        $media->refresh();
        $this->assertSame('New Title', $media->title);
        $this->assertSame('New Alt', $media->alt);
        $this->assertSame('new-collection', $media->collection);
    }

    public function test_throws_exception_for_missing_media(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Media 00000000-0000-0000-0000-000000000000 not found');

        $this->action->execute('00000000-0000-0000-0000-000000000000', [
            'title' => 'New Title',
        ]);
    }

    public function test_updates_soft_deleted_media(): void
    {
        $media = Media::factory()->create();
        $media->delete();

        $updated = $this->action->execute($media->id, [
            'title' => 'Updated Title',
        ]);

        $this->assertSame('Updated Title', $updated->title);
        $this->assertTrue($updated->trashed());
    }

    public function test_handles_partial_updates(): void
    {
        $media = Media::factory()->create([
            'title' => 'Original Title',
            'alt' => 'Original Alt',
            'collection' => 'original-collection',
        ]);

        // Обновляем только title
        $updated = $this->action->execute($media->id, [
            'title' => 'Updated Title',
        ]);

        $this->assertSame('Updated Title', $updated->title);
        $this->assertSame('Original Alt', $updated->alt);
        $this->assertSame('original-collection', $updated->collection);

        // Обновляем только alt
        $updated = $this->action->execute($media->id, [
            'alt' => 'Updated Alt',
        ]);

        $this->assertSame('Updated Title', $updated->title);
        $this->assertSame('Updated Alt', $updated->alt);
        $this->assertSame('original-collection', $updated->collection);
    }

    public function test_normalizes_collection_on_update(): void
    {
        $media = Media::factory()->create([
            'collection' => 'old-collection',
        ]);

        // Collection нормализуется в StoreMediaRequest, но здесь проверяем, что значение сохраняется как есть
        $updated = $this->action->execute($media->id, [
            'collection' => 'New Collection Name',
        ]);

        $this->assertSame('New Collection Name', $updated->collection);
    }
}


