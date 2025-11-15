<?php

namespace Tests\Feature\Admin\Media;

use App\Models\Entry;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\PostType;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('media.disk', 'media');
        config()->set('media.allowed_mimes', [
            'image/jpeg',
            'image/png',
            'image/webp',
            'video/mp4',
            'audio/mpeg',
            'application/pdf',
        ]);
    }

    public function test_it_uploads_image_and_extracts_metadata(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read', 'media.create']);

        $file = UploadedFile::fake()->image('hero.jpg', 1920, 1080)->size(1024);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'title' => 'Hero',
            'alt' => 'Main banner',
            'collection' => 'banners',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Hero');
        $response->assertJsonPath('data.kind', 'image');
        $response->assertHeader('Cache-Control', 'no-store, private');

        $media = Media::first();
        $this->assertNotNull($media);
        $this->assertSame('Hero', $media->title);
        $this->assertSame(1920, $media->width);
        $this->assertSame(1080, $media->height);
        $this->assertSame('image/jpeg', $media->mime);
        Storage::disk('media')->assertExists($media->path);
    }

    public function test_it_validates_mime_and_size_limits(): void
    {
        Storage::fake('media');
        config()->set('media.allowed_mimes', ['image/jpeg']);
        $admin = $this->admin(['media.create']);

        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [], ['file' => $file], $admin);

        $response->assertStatus(422);
        $response->assertJsonPath('type', $this->typeUri(ErrorCode::VALIDATION_ERROR));
        $response->assertJsonPath('code', ErrorCode::VALIDATION_ERROR->value);
        $this->assertValidationErrors($response, ['file']);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_it_lists_media_with_filters_and_pagination(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read']);

        $image = Media::factory()->create([
            'collection' => 'banners',
            'mime' => 'image/jpeg',
        ]);

        Media::factory()->create([
            'collection' => 'gallery',
            'mime' => 'image/jpeg',
        ]);

        Media::factory()->document()->create([
            'collection' => 'docs',
        ]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/media?collection=banners&kind=image&per_page=1', $admin);

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $image->id);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_it_updates_title_alt_collection(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read', 'media.update']);
        $media = Media::factory()->create([
            'title' => null,
            'alt' => null,
            'collection' => null,
        ]);

        $response = $this->putJsonAsAdmin("/api/v1/admin/media/{$media->id}", [
            'title' => 'Updated Title',
            'alt' => 'Updated Alt',
            'collection' => 'updated',
        ], $admin);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'title' => 'Updated Title',
            'alt' => 'Updated Alt',
            'collection' => 'updated',
        ]);
    }

    public function test_it_soft_deletes_and_excludes_from_default_scope(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read', 'media.delete']);
        $media = Media::factory()->create();

        $deleteResponse = $this->deleteJsonAsAdmin("/api/v1/admin/media/{$media->id}", [], $admin);
        $deleteResponse->assertNoContent();

        $this->assertSoftDeleted('media', ['id' => $media->id]);

        $listResponse = $this->getJsonAsAdmin('/api/v1/admin/media', $admin);
        $listResponse->assertOk();
        $listResponse->assertJsonMissing(['id' => $media->id]);
    }

    public function test_it_returns_409_when_media_is_in_use(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read', 'media.delete']);

        $postType = PostType::factory()->create();
        $entry = Entry::factory()->forPostType($postType)->create();
        $media = Media::factory()->create();
        $entry->media()->attach($media->id, ['field_key' => 'gallery', 'order' => 0]);

        $response = $this->deleteJsonAsAdmin("/api/v1/admin/media/{$media->id}", [], $admin);

        $response->assertStatus(409);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => $this->typeUri(ErrorCode::MEDIA_IN_USE),
            'code' => ErrorCode::MEDIA_IN_USE->value,
            'status' => 409,
        ]);
    }

    public function test_it_restores_soft_deleted_media(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read', 'media.restore']);
        $media = Media::factory()->create();
        $media->delete();

        $response = $this->postJsonAsAdmin("/api/v1/admin/media/{$media->id}/restore", [], $admin);

        $response->assertOk();
        $response->assertJsonPath('data.id', $media->id);

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'deleted_at' => null,
        ]);
    }

    public function test_restore_missing_media_returns_problem_payload(): void
    {
        $admin = $this->admin(['media.read', 'media.restore']);

        $response = $this->postJsonAsAdmin('/api/v1/admin/media/missing-id/restore', [], $admin);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => $this->typeUri(ErrorCode::NOT_FOUND),
            'code' => ErrorCode::NOT_FOUND->value,
            'detail' => 'Deleted media with ID missing-id does not exist.',
        ]);
    }

    public function test_it_serves_signed_preview_and_generates_variant_on_demand(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read', 'media.create']);

        $file = UploadedFile::fake()->image('hero.jpg', 600, 400);

        $upload = $this->postMultipartAsAdmin('/api/v1/admin/media', [], ['file' => $file], $admin);
        $upload->assertCreated();

        $mediaId = $upload->json('data.id');
        $media = Media::findOrFail($mediaId);

        $previewResponse = $this->getJsonAsAdmin("/api/v1/admin/media/{$mediaId}/preview?variant=thumbnail", $admin);
        // Для локального диска (fake) возвращается файл напрямую (200), для облачных - редирект (302)
        $previewResponse->assertStatus(200);
        $previewResponse->assertHeader('Content-Type', 'image/jpeg');

        $variant = MediaVariant::where('media_id', $mediaId)
            ->where('variant', 'thumbnail')
            ->first();

        $this->assertNotNull($variant);
        Storage::disk('media')->assertExists($variant->path);

        $downloadResponse = $this->getJsonAsAdmin("/api/v1/admin/media/{$mediaId}/download", $admin);
        // Для локального диска (fake) возвращается файл напрямую (200), для облачных - редирект (302)
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Type', 'image/jpeg');
    }

    private function admin(array $permissions): User
    {
        return User::factory()->create([
            'admin_permissions' => $permissions,
        ]);
    }

    private function typeUri(ErrorCode $code): string
    {
        return config('errors.types.' . $code->value . '.uri');
    }
}


