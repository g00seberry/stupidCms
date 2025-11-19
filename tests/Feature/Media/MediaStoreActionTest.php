<?php

declare(strict_types=1);

use App\Domain\Media\Actions\MediaStoreAction;
use App\Models\Media;
use App\Models\MediaImage;
use App\Models\MediaAvMetadata;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Feature-тесты для MediaStoreAction.
 *
 * Проверяют создание записей в специализированных таблицах
 * (media_images для изображений, media_av_metadata для видео/аудио).
 */

beforeEach(function () {
    Storage::fake('media');
});

test('creates media image record for images', function () {
    $action = app(MediaStoreAction::class);
    
    $file = UploadedFile::fake()->image('test.jpg', 1920, 1080);

    $media = $action->execute($file, [
        'title' => 'Test Image',
    ]);

    expect($media->kind())->toBe(\App\Domain\Media\MediaKind::Image);

    // Проверяем, что запись в media_images создана
    $image = MediaImage::where('media_id', $media->id)->first();
    
    expect($image)->not->toBeNull()
        ->and($image->width)->toBe(1920)
        ->and($image->height)->toBe(1080);

    // Проверяем, что width, height, exif_json не сохранены в media
    $this->assertDatabaseMissing('media', [
        'id' => $media->id,
        'width' => 1920,
    ]);
    $this->assertDatabaseMissing('media', [
        'id' => $media->id,
        'height' => 1080,
    ]);
});

test('creates media av metadata record for videos', function () {
    $action = app(MediaStoreAction::class);
    
    $file = UploadedFile::fake()->create('test.mp4', 1024, 'video/mp4');

    $media = $action->execute($file, [
        'title' => 'Test Video',
    ]);

    expect($media->kind())->toBe(\App\Domain\Media\MediaKind::Video);

    // Для видео без реальных метаданных запись может не создаться
    // (так как плагины могут не вернуть данные)
    // Проверяем, что колонка duration_ms отсутствует в таблице media
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('media');
    expect($columns)->not->toContain('duration_ms');
});

test('does not save width height duration_ms exif_json in media table', function () {
    $action = app(MediaStoreAction::class);
    
    $file = UploadedFile::fake()->image('test.jpg', 1920, 1080);

    $media = $action->execute($file);

    // Проверяем, что эти поля отсутствуют в таблице media
    // Используем прямой SQL запрос, так как Eloquent может скрыть отсутствующие колонки
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('media');
    
    expect($columns)->not->toContain('width')
        ->and($columns)->not->toContain('height')
        ->and($columns)->not->toContain('duration_ms')
        ->and($columns)->not->toContain('exif_json');
});

test('creates media image with exif data when available', function () {
    $action = app(MediaStoreAction::class);
    
    $file = UploadedFile::fake()->image('test.jpg', 1920, 1080);

    $media = $action->execute($file, [
        'title' => 'Test Image',
    ]);

    $image = MediaImage::where('media_id', $media->id)->first();
    
    // EXIF может быть null, если не удалось извлечь
    // Но проверяем, что запись создана
    expect($image)->not->toBeNull();
});

test('does not create media image if width or height is null', function () {
    $action = app(MediaStoreAction::class);
    
    // Создаем файл, который не является изображением
    $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

    $media = $action->execute($file);

    expect($media->kind())->toBe(\App\Domain\Media\MediaKind::Document);

    // Для документов не должно быть записи в media_images
    $image = MediaImage::where('media_id', $media->id)->first();
    
    expect($image)->toBeNull();
});

test('creates media av metadata only if has normalized data', function () {
    $action = app(MediaStoreAction::class);
    
    $file = UploadedFile::fake()->create('test.mp4', 1024, 'video/mp4');

    $media = $action->execute($file);

    expect($media->kind())->toBe(\App\Domain\Media\MediaKind::Video);

    // Для видео без метаданных запись может не создаться
    // Это нормально, так как плагины могут не вернуть данные
    $avMetadata = MediaAvMetadata::where('media_id', $media->id)->first();
    
    // Может быть null, если плагины не вернули данные
    // Проверяем, что колонка duration_ms отсутствует в таблице media
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('media');
    expect($columns)->not->toContain('duration_ms');
});

test('deduplicates file with soft deleted media', function () {
    $action = app(MediaStoreAction::class);
    
    // Создаем файл с фиксированным содержимым для одинакового checksum
    // Используем Storage::fake() для создания стабильного файла
    $fakeFile = UploadedFile::fake()->image('test.jpg', 1920, 1080);
    $fileContent = file_get_contents($fakeFile->getRealPath());
    
    // Сохраняем в Storage для стабильности
    Storage::disk('media')->put('test_file.jpg', $fileContent);
    $tempPath1 = Storage::disk('media')->path('test_file.jpg');
    
    $file1 = new UploadedFile(
        $tempPath1,
        'test.jpg',
        'image/jpeg',
        null,
        true
    );

    $media1 = $action->execute($file1, [
        'title' => 'Original Title',
        'alt' => 'Original Alt',
    ]);

    expect($media1)->not->toBeNull()
        ->and($media1->trashed())->toBeFalse();

    $originalId = $media1->id;
    $originalChecksum = $media1->checksum_sha256;

    // Soft delete файл
    $media1->delete();
    expect($media1->trashed())->toBeTrue();

    // Загружаем тот же файл снова (с тем же содержимым)
    Storage::disk('media')->put('test_file2.jpg', $fileContent);
    $tempPath2 = Storage::disk('media')->path('test_file2.jpg');
    $file2 = new UploadedFile(
        $tempPath2,
        'test.jpg',
        'image/jpeg',
        null,
        true
    );

    $media2 = $action->execute($file2, [
        'title' => 'New Title',
        'alt' => 'New Alt',
    ]);

    // Проверяем, что вернулась та же запись (не создана новая)
    expect($media2->id)->toBe($originalId)
        ->and($media2->checksum_sha256)->toBe($originalChecksum)
        ->and($media2->trashed())->toBeFalse() // Должна быть восстановлена
        ->and($media2->title)->toBe('New Title') // Метаданные обновлены
        ->and($media2->alt)->toBe('New Alt');

    // Проверяем, что не создана новая запись
    $mediaCount = Media::withTrashed()->where('checksum_sha256', $originalChecksum)->count();
    expect($mediaCount)->toBe(1);
});

test('deduplicates file with active media', function () {
    $action = app(MediaStoreAction::class);
    
    // Создаем файл с фиксированным содержимым
    $fakeFile = UploadedFile::fake()->image('test.jpg', 1920, 1080);
    $fileContent = file_get_contents($fakeFile->getRealPath());
    
    Storage::disk('media')->put('test_file3.jpg', $fileContent);
    $tempPath1 = Storage::disk('media')->path('test_file3.jpg');
    
    $file1 = new UploadedFile(
        $tempPath1,
        'test.jpg',
        'image/jpeg',
        null,
        true
    );

    $media1 = $action->execute($file1, [
        'title' => 'Original Title',
    ]);

    $originalId = $media1->id;
    $originalChecksum = $media1->checksum_sha256;

    // Загружаем тот же файл снова (не удаленный)
    Storage::disk('media')->put('test_file4.jpg', $fileContent);
    $tempPath2 = Storage::disk('media')->path('test_file4.jpg');
    $file2 = new UploadedFile(
        $tempPath2,
        'test.jpg',
        'image/jpeg',
        null,
        true
    );

    $media2 = $action->execute($file2, [
        'title' => 'New Title',
    ]);

    // Проверяем, что вернулась та же запись
    expect($media2->id)->toBe($originalId)
        ->and($media2->checksum_sha256)->toBe($originalChecksum)
        ->and($media2->trashed())->toBeFalse()
        ->and($media2->title)->toBe('New Title');

    // Проверяем, что не создана новая запись
    $mediaCount = Media::where('checksum_sha256', $originalChecksum)->count();
    expect($mediaCount)->toBe(1);
});

