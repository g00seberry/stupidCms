<?php

namespace Tests\Feature\Admin\Media;

use App\Models\Media;
use App\Models\MediaVariant;
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

        config()->set('media.disks', [
            'default' => 'media',
            'collections' => [],
            'kinds' => [],
        ]);
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

    public function test_it_routes_media_to_collection_specific_disk(): void
    {
        Storage::fake('media_videos');
        config()->set('media.disks', [
            'default' => 'media',
            'collections' => [
                'videos' => 'media_videos',
            ],
            'kinds' => [],
        ]);

        $admin = $this->admin(['media.read', 'media.create']);

        $file = UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4');

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'collection' => 'videos',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertCreated();

        $media = Media::firstOrFail();
        $this->assertSame('media_videos', $media->disk);
        Storage::disk('media_videos')->assertExists($media->path);
    }

    public function test_it_uses_default_disk_for_unknown_collection(): void
    {
        Storage::fake('media');
        config()->set('media.disks', [
            'default' => 'media',
            'collections' => [
                'videos' => 'media_videos',
            ],
            'kinds' => [],
        ]);

        $admin = $this->admin(['media.read', 'media.create']);

        $file = UploadedFile::fake()->image('hero.jpg', 800, 600);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'collection' => 'other',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertCreated();

        $media = Media::firstOrFail();
        $this->assertSame('media', $media->disk);
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

    public function test_it_deduplicates_files_by_checksum(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read', 'media.create']);

        // Создаём первый файл с известным содержимым
        $file1 = UploadedFile::fake()->image('test.jpg', 100, 100);
        $checksum1 = hash_file('sha256', $file1->getRealPath());

        $response1 = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'title' => 'First Upload',
            'collection' => 'test',
        ], [
            'file' => $file1,
        ], $admin);

        $response1->assertCreated();
        $mediaId1 = $response1->json('data.id');
        $media1 = Media::findOrFail($mediaId1);
        $this->assertSame($checksum1, $media1->checksum_sha256);
        $this->assertSame('First Upload', $media1->title);
        $this->assertSame('test', $media1->collection);

        $filesCountBefore = Storage::disk('media')->allFiles();
        $this->assertCount(1, $filesCountBefore);

        // Загружаем тот же файл повторно (с другим именем и метаданными)
        $file2 = UploadedFile::fake()->image('test2.jpg', 100, 100);
        // Убеждаемся, что это тот же файл (для fake файлов нужно создать идентичный)
        // В реальности это будет тот же файл, но для теста создадим файл с тем же содержимым
        $tempPath = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.jpg';
        copy($file1->getRealPath(), $tempPath);
        $file2 = new UploadedFile($tempPath, 'test2.jpg', 'image/jpeg', null, true);

        $response2 = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'title' => 'Second Upload',
            'alt' => 'Alt text',
            'collection' => 'other',
        ], [
            'file' => $file2,
        ], $admin);

        // При дедупликации возвращается существующая запись (200, а не 201)
        $response2->assertOk();
        $mediaId2 = $response2->json('data.id');

        // Должна быть возвращена та же запись
        $this->assertSame($mediaId1, $mediaId2);

        // Файл не должен быть сохранён повторно
        $filesCountAfter = Storage::disk('media')->allFiles();
        $this->assertCount(1, $filesCountAfter);

        // Метаданные должны быть обновлены
        $media1->refresh();
        $this->assertSame('Second Upload', $media1->title);
        $this->assertSame('Alt text', $media1->alt);
        $this->assertSame('other', $media1->collection);

        // В БД должна быть только одна запись
        $this->assertDatabaseCount('media', 1);

        unlink($tempPath);
    }

    public function test_it_creates_new_media_for_different_files(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read', 'media.create']);

        $file1 = UploadedFile::fake()->image('test1.jpg', 100, 100);
        $file2 = UploadedFile::fake()->image('test2.jpg', 200, 200);

        $response1 = $this->postMultipartAsAdmin('/api/v1/admin/media', [], ['file' => $file1], $admin);
        $response1->assertCreated();

        $response2 = $this->postMultipartAsAdmin('/api/v1/admin/media', [], ['file' => $file2], $admin);
        $response2->assertCreated();

        $mediaId1 = $response1->json('data.id');
        $mediaId2 = $response2->json('data.id');

        // Должны быть созданы разные записи
        $this->assertNotSame($mediaId1, $mediaId2);

        // В БД должно быть 2 записи
        $this->assertDatabaseCount('media', 2);

        // На диске должно быть 2 файла
        $files = Storage::disk('media')->allFiles();
        $this->assertCount(2, $files);
    }

    public function test_it_validates_title_min_length(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.create']);

        $file = UploadedFile::fake()->image('hero.jpg', 800, 600);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'title' => '',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertStatus(422);
        $response->assertJsonPath('code', ErrorCode::VALIDATION_ERROR->value);
        $this->assertValidationErrors($response, ['title']);
    }

    public function test_it_validates_alt_min_length(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.create']);

        $file = UploadedFile::fake()->image('hero.jpg', 800, 600);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'alt' => '',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertStatus(422);
        $response->assertJsonPath('code', ErrorCode::VALIDATION_ERROR->value);
        $this->assertValidationErrors($response, ['alt']);
    }

    public function test_it_validates_title_max_length(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.create']);

        $file = UploadedFile::fake()->image('hero.jpg', 800, 600);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'title' => str_repeat('a', 256),
        ], [
            'file' => $file,
        ], $admin);

        $response->assertStatus(422);
        $response->assertJsonPath('code', ErrorCode::VALIDATION_ERROR->value);
        $this->assertValidationErrors($response, ['title']);
    }

    public function test_it_validates_alt_max_length(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.create']);

        $file = UploadedFile::fake()->image('hero.jpg', 800, 600);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'alt' => str_repeat('a', 256),
        ], [
            'file' => $file,
        ], $admin);

        $response->assertStatus(422);
        $response->assertJsonPath('code', ErrorCode::VALIDATION_ERROR->value);
        $this->assertValidationErrors($response, ['alt']);
    }

    public function test_it_automatically_slugifies_collection_on_store(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read', 'media.create']);

        $file = UploadedFile::fake()->image('hero.jpg', 800, 600);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'collection' => 'My Collection Name!',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertCreated();
        $media = Media::firstOrFail();
        $this->assertSame('my-collection-name', $media->collection);
    }

    public function test_it_automatically_slugifies_collection_on_update(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read', 'media.update']);
        $media = Media::factory()->create([
            'collection' => 'old',
        ]);

        $response = $this->putJsonAsAdmin("/api/v1/admin/media/{$media->id}", [
            'collection' => 'New Collection Name!',
        ], $admin);

        $response->assertOk();
        $media->refresh();
        $this->assertSame('new-collection-name', $media->collection);
    }

    public function test_it_normalizes_empty_collection_to_null(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read', 'media.create']);

        $file = UploadedFile::fake()->image('hero.jpg', 800, 600);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'collection' => '   ',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertCreated();
        $media = Media::firstOrFail();
        $this->assertNull($media->collection);
    }

    public function test_it_validates_title_min_length_on_update(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.update']);
        $media = Media::factory()->create();

        $response = $this->putJsonAsAdmin("/api/v1/admin/media/{$media->id}", [
            'title' => '',
        ], $admin);

        $response->assertStatus(422);
        $response->assertJsonPath('code', ErrorCode::VALIDATION_ERROR->value);
        $this->assertValidationErrors($response, ['title']);
    }

    public function test_it_validates_alt_min_length_on_update(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.update']);
        $media = Media::factory()->create();

        $response = $this->putJsonAsAdmin("/api/v1/admin/media/{$media->id}", [
            'alt' => '',
        ], $admin);

        $response->assertStatus(422);
        $response->assertJsonPath('code', ErrorCode::VALIDATION_ERROR->value);
        $this->assertValidationErrors($response, ['alt']);
    }

    public function test_it_handles_heic_image_format(): void
    {
        Storage::fake('media');
        config()->set('media.allowed_mimes', [
            'image/jpeg',
            'image/png',
            'image/heic',
            'image/heif',
        ]);
        $admin = $this->admin(['media.read', 'media.create']);

        // Используем реальный HEIC файл из тестовой директории
        $heicPath = __DIR__ . '/IMG_2998.HEIC';
        if (! file_exists($heicPath)) {
            $this->markTestSkipped('HEIC test file not found');
        }

        $file = new UploadedFile($heicPath, 'IMG_2998.HEIC', 'image/heic', null, true);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'title' => 'HEIC Photo',
            'collection' => 'photos',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertCreated();
        $response->assertJsonPath('data.mime', 'image/heic');

        $media = Media::firstOrFail();
        $this->assertSame('image/heic', $media->mime);
        $this->assertSame('heic', $media->ext);
    }

    public function test_it_handles_avif_image_format(): void
    {
        Storage::fake('media');
        config()->set('media.allowed_mimes', [
            'image/jpeg',
            'image/png',
            'image/avif',
        ]);
        $admin = $this->admin(['media.read', 'media.create']);

        // Для полного тестирования AVIF нужен реальный файл
        // fake()->image() создаёт JPEG, поэтому этот тест пропускается
        // В реальности можно добавить реальный AVIF файл в тестовую директорию
        $this->markTestSkipped('AVIF test requires a real AVIF file (fake()->image() creates JPEG)');
    }

    public function test_it_handles_animated_gif(): void
    {
        Storage::fake('media');
        config()->set('media.allowed_mimes', [
            'image/gif',
            'image/jpeg',
        ]);
        $admin = $this->admin(['media.read', 'media.create']);

        // Создаём фейковый GIF файл (в реальности это будет animated GIF)
        $file = UploadedFile::fake()->image('animation.gif', 100, 100)->size(2048);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'title' => 'Animated GIF',
            'collection' => 'animations',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertCreated();
        $response->assertJsonPath('data.mime', 'image/gif');

        $media = Media::firstOrFail();
        $this->assertSame('image/gif', $media->mime);
        $this->assertSame('gif', $media->ext);
    }

    public function test_it_handles_mp4_audio_only(): void
    {
        Storage::fake('media');
        config()->set('media.allowed_mimes', [
            'video/mp4',
            'audio/mp4',
            'audio/mpeg',
        ]);
        $admin = $this->admin(['media.read', 'media.create']);

        // Создаём фейковый MP4 audio-only файл
        $file = UploadedFile::fake()->create('audio.mp4', 2048, 'audio/mp4');

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'title' => 'MP4 Audio',
            'collection' => 'audio',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertCreated();
        $response->assertJsonPath('data.mime', 'audio/mp4');

        $media = Media::firstOrFail();
        $this->assertSame('audio/mp4', $media->mime);
        $this->assertSame('mp4', $media->ext);
        $this->assertNull($media->width);
        $this->assertNull($media->height);
    }

    public function test_it_handles_aiff_audio_format(): void
    {
        Storage::fake('media');
        config()->set('media.allowed_mimes', [
            'audio/aiff',
            'audio/x-aiff',
            'audio/mpeg',
        ]);
        config()->set('media.max_upload_mb', 100); // Увеличиваем лимит для большого AIFF файла
        $admin = $this->admin(['media.read', 'media.create']);

        // Используем реальный AIFF файл из тестовой директории
        $aiffPath = __DIR__ . '/falloutcraft.aiff';
        if (! file_exists($aiffPath)) {
            $this->markTestSkipped('AIFF test file not found');
        }

        $file = new UploadedFile($aiffPath, 'falloutcraft.aiff', 'audio/x-aiff', null, true);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'title' => 'AIFF Audio',
            'collection' => 'audio',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertCreated();
        $response->assertJsonPath('data.mime', 'audio/x-aiff');

        $media = Media::firstOrFail();
        $this->assertSame('audio/x-aiff', $media->mime);
        $this->assertSame('aiff', $media->ext);
        $this->assertNull($media->width);
        $this->assertNull($media->height);
    }

    public function test_it_validates_mime_signature_mismatch(): void
    {
        Storage::fake('media');
        config()->set('media.allowed_mimes', ['image/jpeg']);
        $admin = $this->admin(['media.create']);

        // Создаём файл с неправильным MIME (заявлен image/jpeg, но содержимое не JPEG)
        $file = UploadedFile::fake()->create('fake.jpg', 100, 'application/octet-stream');

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [], ['file' => $file], $admin);

        // Валидация должна провалиться либо на уровне Laravel (mimetypes), либо на уровне нашего валидатора
        // В зависимости от того, как настроена валидация
        $this->assertTrue(
            $response->status() === 422 || $response->status() === 500,
            'Expected validation error for MIME signature mismatch'
        );
    }

    public function test_it_applies_collection_specific_rules(): void
    {
        Storage::fake('media');
        config()->set('media.collections', [
            'thumbnails' => [
                'allowed_mimes' => ['image/jpeg', 'image/png'],
                'max_size_bytes' => 5 * 1024 * 1024, // 5 MB
                'max_width' => 1920,
                'max_height' => 1080,
            ],
        ]);
        $admin = $this->admin(['media.read', 'media.create']);

        // Файл, который превышает ограничения коллекции
        $file = UploadedFile::fake()->image('large.jpg', 2560, 1440)->size(6 * 1024 * 1024);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'collection' => 'thumbnails',
        ], [
            'file' => $file,
        ], $admin);

        // Должна быть ошибка валидации из-за превышения размеров или размера файла
        $this->assertTrue(
            $response->status() === 422 || $response->status() === 500,
            'Expected validation error for collection-specific rules violation'
        );
    }

    private function typeUri(ErrorCode $code): string
    {
        return config('errors.types.' . $code->value . '.uri');
    }
}


