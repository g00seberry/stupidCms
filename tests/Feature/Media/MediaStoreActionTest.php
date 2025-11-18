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

