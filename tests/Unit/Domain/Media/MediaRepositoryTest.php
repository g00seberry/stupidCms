<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media;

use App\Domain\Media\EloquentMediaRepository;
use App\Domain\Media\MediaDeletedFilter;
use App\Domain\Media\MediaQuery;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MediaRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_kind_document_excludes_media_types(): void
    {
        Media::factory()->image()->create();
        $doc = Media::factory()->document()->create(['mime' => 'application/pdf']);

        $repo = app(EloquentMediaRepository::class);
        $q = new MediaQuery(search: null, kind: 'document', mimePrefix: null, collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted, sort: 'created_at', order: 'desc', page: 1, perPage: 50);

        $result = $repo->get($q);
        $this->assertCount(1, $result);
        $this->assertSame($doc->id, $result->first()->id);
    }

    public function test_mime_prefix_filters(): void
    {
        Media::factory()->image()->create(['mime' => 'image/png', 'ext' => 'png']);
        Media::factory()->image()->create(['mime' => 'image/jpeg', 'ext' => 'jpg']);

        $repo = app(EloquentMediaRepository::class);
        $q = new MediaQuery(search: null, kind: null, mimePrefix: 'image/png', collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted, sort: 'created_at', order: 'desc', page: 1, perPage: 50);

        $result = $repo->get($q);
        $this->assertCount(1, $result);
        $this->assertSame('image/png', $result->first()->mime);
    }
}


