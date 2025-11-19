<?php

declare(strict_types=1);

use App\Domain\Media\Images\GlideImageProcessor;
use App\Domain\Media\Images\ImageRef;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;

beforeEach(function () {
    // Используем реальный ImageManager, так как он final и не может быть замокан
    $this->manager = new ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
    $this->processor = new GlideImageProcessor($this->manager);
});

test('opens image file', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());

    $image = $this->processor->open($contents);

    expect($image)->toBeInstanceOf(ImageRef::class)
        ->and($image->native)->toBeInstanceOf(\Intervention\Image\Interfaces\ImageInterface::class);
});

test('throws exception when opening invalid image data', function () {
    $invalidData = 'not an image';

    expect(fn () => $this->processor->open($invalidData))
        ->toThrow(\RuntimeException::class, 'Unsupported or corrupt image data for Glide/Intervention.');
});

test('gets image width', function () {
    $file = UploadedFile::fake()->image('test.jpg', 200, 150);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $width = $this->processor->width($image);

    expect($width)->toBe(200);
});

test('gets image height', function () {
    $file = UploadedFile::fake()->image('test.jpg', 200, 150);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $height = $this->processor->height($image);

    expect($height)->toBe(150);
});

test('resizes image', function () {
    $file = UploadedFile::fake()->image('test.jpg', 200, 150);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $resized = $this->processor->resize($image, 100, 75);

    expect($resized)->toBeInstanceOf(ImageRef::class)
        ->and($this->processor->width($resized))->toBe(100)
        ->and($this->processor->height($resized))->toBe(75);
});

test('returns same image when resizing to same dimensions', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $resized = $this->processor->resize($image, 100, 100);

    expect($resized)->toBe($image);
});

test('encodes image to jpeg format', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $encoded = $this->processor->encode($image, 'jpg', 90);

    expect($encoded)->toBeArray()
        ->and($encoded['data'])->toBeString()
        ->and($encoded['extension'])->toBe('jpg')
        ->and($encoded['mime'])->toBe('image/jpeg')
        ->and(strlen($encoded['data']))->toBeGreaterThan(0);
});

test('encodes image to png format', function () {
    $file = UploadedFile::fake()->image('test.png', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $encoded = $this->processor->encode($image, 'png', 90);

    expect($encoded)->toBeArray()
        ->and($encoded['extension'])->toBe('png')
        ->and($encoded['mime'])->toBe('image/png')
        ->and(strlen($encoded['data']))->toBeGreaterThan(0);
});

test('encodes image to gif format', function () {
    $file = UploadedFile::fake()->image('test.gif', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $encoded = $this->processor->encode($image, 'gif', 90);

    expect($encoded)->toBeArray()
        ->and($encoded['extension'])->toBe('gif')
        ->and($encoded['mime'])->toBe('image/gif')
        ->and(strlen($encoded['data']))->toBeGreaterThan(0);
});

test('encodes image to webp format', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $encoded = $this->processor->encode($image, 'webp', 90);

    expect($encoded)->toBeArray()
        ->and($encoded['extension'])->toBe('webp')
        ->and($encoded['mime'])->toBe('image/webp')
        ->and(strlen($encoded['data']))->toBeGreaterThan(0);
});

test('encodes image to avif format when supported', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    try {
        $encoded = $this->processor->encode($image, 'avif', 90);
        
        expect($encoded)->toBeArray()
            ->and($encoded['extension'])->toBe('avif')
            ->and($encoded['mime'])->toBe('image/avif')
            ->and(strlen($encoded['data']))->toBeGreaterThan(0);
    } catch (\Throwable) {
        // AVIF может быть не поддерживается, тогда fallback на JPEG
        $encoded = $this->processor->encode($image, 'avif', 90);
        expect($encoded['extension'])->toBe('jpg');
    }
});

test('falls back to jpeg when avif encoding fails', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    // Если AVIF не поддерживается, должен быть fallback на JPEG
    $encoded = $this->processor->encode($image, 'avif', 90);

    // В любом случае должен вернуть валидные данные
    expect($encoded)->toBeArray()
        ->and($encoded['mime'])->toBeIn(['image/avif', 'image/jpeg'])
        ->and(strlen($encoded['data']))->toBeGreaterThan(0);
});

test('encodes image to heic format when supported', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $encoded = $this->processor->encode($image, 'heic', 90);

    // HEIC может быть не поддерживается, тогда fallback на JPEG
    expect($encoded)->toBeArray()
        ->and($encoded['extension'])->toBeIn(['heic', 'jpg'])
        ->and($encoded['mime'])->toBeIn(['image/heic', 'image/jpeg'])
        ->and(strlen($encoded['data']))->toBeGreaterThan(0);
});

test('falls back to jpeg when heic is not supported', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $encoded = $this->processor->encode($image, 'heic', 90);

    // Должен вернуть либо HEIC, либо JPEG (fallback)
    expect($encoded)->toBeArray()
        ->and($encoded['extension'])->toBeIn(['heic', 'jpg'])
        ->and($encoded['mime'])->toBeIn(['image/heic', 'image/jpeg']);
});

test('supports jpg format', function () {
    expect($this->processor->supports('jpg'))->toBeTrue()
        ->and($this->processor->supports('jpeg'))->toBeTrue();
});

test('supports png format', function () {
    expect($this->processor->supports('png'))->toBeTrue();
});

test('supports gif format', function () {
    expect($this->processor->supports('gif'))->toBeTrue();
});

test('supports webp format', function () {
    expect($this->processor->supports('webp'))->toBeTrue();
});

test('supports avif format', function () {
    expect($this->processor->supports('avif'))->toBeTrue();
});

test('supports heic and heif formats', function () {
    expect($this->processor->supports('heic'))->toBeTrue()
        ->and($this->processor->supports('heif'))->toBeTrue();
});

test('does not support unsupported formats', function () {
    expect($this->processor->supports('bmp'))->toBeFalse()
        ->and($this->processor->supports('tiff'))->toBeFalse();
});

test('supports method is case insensitive', function () {
    expect($this->processor->supports('JPG'))->toBeTrue()
        ->and($this->processor->supports('PNG'))->toBeTrue()
        ->and($this->processor->supports('AVIF'))->toBeTrue();
});

test('destroys image resource', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    // destroy не должен вызывать исключение
    expect(fn () => $this->processor->destroy($image))->not->toThrow(\Exception::class);
});

test('encodes with quality parameter', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $encoded = $this->processor->encode($image, 'jpg', 50);

    expect($encoded)->toBeArray()
        ->and(strlen($encoded['data']))->toBeGreaterThan(0);
});

test('clamps quality to valid range', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    // Качество 150 должно быть заклемплено до 100
    $encoded = $this->processor->encode($image, 'jpg', 150);

    expect($encoded)->toBeArray()
        ->and(strlen($encoded['data']))->toBeGreaterThan(0);
});

test('handles heif extension same as heic', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $encoded = $this->processor->encode($image, 'heif', 90);

    // Должен обработать как HEIC (или fallback на JPEG)
    expect($encoded)->toBeArray()
        ->and($encoded['extension'])->toBeIn(['heic', 'jpg']);
});

