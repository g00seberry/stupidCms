<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Domain\Media\Events\MediaDeleted;
use App\Domain\Media\Events\MediaProcessed;
use App\Domain\Media\Events\MediaUploaded;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Tests\Support\MediaTestCase;

/**
 * Тесты для событий медиа-файлов.
 *
 * Проверяет диспатч событий MediaUploaded, MediaProcessed и MediaDeleted
 * при соответствующих операциях с медиа-файлами.
 */
class MediaEventsTest extends MediaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('media.variants', [
            'thumbnail' => ['max' => 150],
        ]);
    }

    /**
     * Тест: событие MediaUploaded отправляется при загрузке медиа-файла.
     */
    public function test_it_dispatches_media_uploaded_event_on_upload(): void
    {
        Event::fake([MediaUploaded::class]);

        $admin = $this->admin(['media.read', 'media.create']);

        $file = UploadedFile::fake()->image('hero.jpg', 1920, 1080)->size(1024);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'title' => 'Hero',
            'alt' => 'Main banner',
        ], [
            'file' => $file,
        ], $admin);

        $response->assertCreated();

        Event::assertDispatched(MediaUploaded::class, function (MediaUploaded $event) {
            $media = $event->media;
            return $media->title === 'Hero'
                && $media->mime === 'image/jpeg';
        });
    }

    /**
     * Тест: событие MediaProcessed отправляется при генерации варианта.
     */
    public function test_it_dispatches_media_processed_event_on_variant_generation(): void
    {
        Event::fake([MediaProcessed::class]);

        $admin = $this->admin(['media.read', 'media.create', 'media.update']);

        $file = UploadedFile::fake()->image('hero.jpg', 1920, 1080)->size(1024);

        // Загружаем медиа-файл
        $uploadResponse = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'file' => $file,
        ], [
            'file' => $file,
        ], $admin);

        $uploadResponse->assertCreated();
        $mediaId = $uploadResponse->json('data.id');

        // Запрашиваем вариант (генерируется синхронно в тестах)
        $previewResponse = $this->getAsAdmin("/api/v1/admin/media/{$mediaId}/preview?variant=thumbnail", $admin);
        $previewResponse->assertOk();

        Event::assertDispatched(MediaProcessed::class, function (MediaProcessed $event) use ($mediaId) {
            return $event->media->id === $mediaId
                && $event->variant->variant === 'thumbnail';
        });
    }

    /**
     * Тест: событие MediaDeleted отправляется при удалении медиа-файла.
     */
    public function test_it_dispatches_media_deleted_event_on_delete(): void
    {
        Event::fake([MediaDeleted::class]);

        $admin = $this->admin(['media.read', 'media.create', 'media.delete']);

        $file = UploadedFile::fake()->image('hero.jpg', 1920, 1080)->size(1024);

        // Загружаем медиа-файл
        $uploadResponse = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'file' => $file,
        ], [
            'file' => $file,
        ], $admin);

        $uploadResponse->assertCreated();
        $mediaId = $uploadResponse->json('data.id');

        // Удаляем медиа-файл
        $deleteResponse = $this->deleteAsAdmin("/api/v1/admin/media/{$mediaId}", $admin);
        $deleteResponse->assertNoContent();

        Event::assertDispatched(MediaDeleted::class, function (MediaDeleted $event) use ($mediaId) {
            return $event->media->id === $mediaId;
        });
    }

    /**
     * Тест: событие MediaUploaded не отправляется при дедупликации.
     */
    public function test_it_does_not_dispatch_media_uploaded_on_deduplication(): void
    {
        Event::fake([MediaUploaded::class]);

        $admin = $this->admin(['media.read', 'media.create']);

        $file = UploadedFile::fake()->image('hero.jpg', 1920, 1080)->size(1024);

        // Первая загрузка
        $firstResponse = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'file' => $file,
        ], [
            'file' => $file,
        ], $admin);

        $firstResponse->assertCreated();
        Event::assertDispatched(MediaUploaded::class, 1);

        // Вторая загрузка того же файла (дедупликация)
        $file2 = UploadedFile::fake()->image('hero.jpg', 1920, 1080)->size(1024);
        // Создаём файл с тем же содержимым для дедупликации
        $secondResponse = $this->postMultipartAsAdmin('/api/v1/admin/media', [
            'file' => $file2,
        ], [
            'file' => $file2,
        ], $admin);

        $secondResponse->assertOk(); // 200, не 201

        // Событие не должно быть отправлено повторно
        Event::assertDispatched(MediaUploaded::class, 1);
    }


    /**
     * Выполнить DELETE запрос как админ.
     *
     * @param string $uri URI для запроса
     * @param \App\Models\User $user Пользователь
     * @return \Illuminate\Testing\TestResponse
     */
    private function deleteAsAdmin(string $uri, User $user): \Illuminate\Testing\TestResponse
    {
        return $this->deleteJsonAsAdmin($uri, [], $user);
    }

    /**
     * Выполнить GET запрос как админ.
     *
     * @param string $uri URI для запроса
     * @param \App\Models\User $user Пользователь
     * @return \Illuminate\Testing\TestResponse
     */
    private function getAsAdmin(string $uri, User $user): \Illuminate\Testing\TestResponse
    {
        return $this->getJsonAsAdmin($uri, $user);
    }
}

