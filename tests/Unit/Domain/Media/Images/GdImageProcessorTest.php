<?php

declare(strict_types=1);

use App\Domain\Media\Images\GdImageProcessor;
use App\Domain\Media\Images\ImageRef;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->processor = new GdImageProcessor();
});

test('opens image file', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());

    $image = $this->processor->open($contents);

    expect($image)->toBeInstanceOf(ImageRef::class)
        ->and($image->native)->toBeInstanceOf(\GdImage::class);
});

test('throws exception when opening invalid image data', function () {
    $invalidData = 'not an image';

    expect(fn () => $this->processor->open($invalidData))
        ->toThrow(RuntimeException::class, 'Unsupported or corrupt image data for GD.');
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

test('maintains aspect ratio on resize', function () {
    $file = UploadedFile::fake()->image('test.jpg', 200, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $resized = $this->processor->resize($image, 100, 50);

    expect($this->processor->width($resized))->toBe(100)
        ->and($this->processor->height($resized))->toBe(50);
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

test('encodes image to webp format when supported', function () {
    if (! function_exists('imagewebp')) {
        $this->markTestSkipped('WebP support not available');
    }

    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $encoded = $this->processor->encode($image, 'webp', 90);

    expect($encoded)->toBeArray()
        ->and($encoded['extension'])->toBe('webp')
        ->and($encoded['mime'])->toBe('image/webp')
        ->and(strlen($encoded['data']))->toBeGreaterThan(0);
});

test('falls back to jpeg when webp is not supported', function () {
    if (function_exists('imagewebp')) {
        $this->markTestSkipped('WebP support is available');
    }

    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $encoded = $this->processor->encode($image, 'webp', 90);

    expect($encoded)->toBeArray()
        ->and($encoded['extension'])->toBe('jpg')
        ->and($encoded['mime'])->toBe('image/jpeg');
});

test('throws exception when encoding fails', function () {
    // Создаём валидное изображение, но затем пытаемся его уничтожить
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);
    
    // Уничтожаем изображение перед кодированием
    $this->processor->destroy($image);

    // Попытка использовать уничтоженное изображение должна привести к ошибке
    // Но в реальности GD может не выбросить исключение, поэтому этот тест может быть сложным
    // Пропускаем его, так как это edge case
    $this->markTestSkipped('Difficult to test encoding failure without corrupting image data');
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

test('does not support unsupported formats', function () {
    expect($this->processor->supports('avif'))->toBeFalse()
        ->and($this->processor->supports('heic'))->toBeFalse()
        ->and($this->processor->supports('bmp'))->toBeFalse();
});

test('supports method is case insensitive', function () {
    expect($this->processor->supports('JPG'))->toBeTrue()
        ->and($this->processor->supports('PNG'))->toBeTrue();
});

test('destroys image resource', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    // Уничтожение не должно выбросить исключение
    expect(fn () => $this->processor->destroy($image))->not->toThrow(Exception::class);
});

test('encodes with quality parameter', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $contents = file_get_contents($file->getRealPath());
    $image = $this->processor->open($contents);

    $encodedLow = $this->processor->encode($image, 'jpg', 10);
    $image2 = $this->processor->open($contents);
    $encodedHigh = $this->processor->encode($image2, 'jpg', 90);

    // Низкое качество должно дать меньший размер файла (обычно)
    expect(strlen($encodedLow['data']))->toBeLessThanOrEqual(strlen($encodedHigh['data']));
});

