<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\MediaResource;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MediaResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_formats_fields_and_types(): void
    {
        $media = Media::factory()->image()->create([
            'title' => 'Hero',
            'alt' => 'Alt',
            'collection' => 'banners',
            'size_bytes' => 12345,
            'width' => 600,
            'height' => 400,
        ]);

        $json = (new MediaResource($media))->toArray(request());
        $this->assertSame($media->id, $json['id']);
        $this->assertIsInt($json['size_bytes']);
        $this->assertIsInt($json['width']);
        $this->assertIsInt($json['height']);
        $this->assertArrayHasKey('preview_urls', $json);
        $this->assertArrayHasKey('download_url', $json);
        $this->assertNotNull($json['created_at']);
    }
}


