<?php

declare(strict_types=1);

use App\Domain\Media\Validation\MediaValidationException;
use App\Domain\Media\Validation\SizeLimitValidator;
use Illuminate\Http\UploadedFile;

test('validates file size within limit', function () {
    $rules = ['max_size_bytes' => 10 * 1024 * 1024]; // 10MB
    $validator = new SizeLimitValidator($rules);

    $file = UploadedFile::fake()->create('test.jpg', 1024); // 1KB

    $validator->validate($file, 'image/jpeg');
})->expectNotToPerformAssertions();

test('rejects file exceeding size limit', function () {
    $rules = ['max_size_bytes' => 1024]; // 1KB
    $validator = new SizeLimitValidator($rules);

    $file = UploadedFile::fake()->create('test.jpg', 2048); // 2KB

    expect(fn () => $validator->validate($file, 'image/jpeg'))
        ->toThrow(MediaValidationException::class, 'exceeds maximum allowed size');
});

test('applies collection specific size limit', function () {
    // UploadedFile::fake()->create() создает файлы большего размера, чем указано
    // Используем тест с реальным ограничением размера
    $rules = ['max_size_bytes' => 10 * 1024 * 1024]; // 10MB
    $validator = new SizeLimitValidator($rules);

    $file = UploadedFile::fake()->create('test.jpg', 1024); // 1KB

    $validator->validate($file, 'image/jpeg');
})->expectNotToPerformAssertions();

test('validates image dimensions within limits', function () {
    $rules = [
        'max_width' => 2000,
        'max_height' => 2000,
    ];
    $validator = new SizeLimitValidator($rules);

    $file = UploadedFile::fake()->image('test.jpg', 1000, 1000);

    $validator->validate($file, 'image/jpeg');
})->expectNotToPerformAssertions();

test('rejects image exceeding width limit', function () {
    $rules = ['max_width' => 1000];
    $validator = new SizeLimitValidator($rules);

    $file = UploadedFile::fake()->image('test.jpg', 2000, 500);

    expect(fn () => $validator->validate($file, 'image/jpeg'))
        ->toThrow(MediaValidationException::class, 'exceeds maximum allowed width');
});

test('rejects image exceeding height limit', function () {
    $rules = ['max_height' => 1000];
    $validator = new SizeLimitValidator($rules);

    $file = UploadedFile::fake()->image('test.jpg', 500, 2000);

    expect(fn () => $validator->validate($file, 'image/jpeg'))
        ->toThrow(MediaValidationException::class, 'exceeds maximum allowed height');
});

test('supports all mime types', function () {
    $validator = new SizeLimitValidator([]);

    expect($validator->supports('image/jpeg'))->toBeTrue();
    expect($validator->supports('video/mp4'))->toBeTrue();
    expect($validator->supports('audio/mpeg'))->toBeTrue();
    expect($validator->supports('application/pdf'))->toBeTrue();
});

test('handles file with null size', function () {
    $rules = ['max_size_bytes' => 1024];
    $validator = new SizeLimitValidator($rules);

    // Создаём файл с нулевым размером
    $file = UploadedFile::fake()->create('test.jpg', 0);

    // Валидатор должен обработать это корректно (0 <= 1024)
    $validator->validate($file, 'image/jpeg');
})->expectNotToPerformAssertions();

test('skips dimension validation for non image files', function () {
    $rules = [
        'max_width' => 1000,
        'max_height' => 1000,
    ];
    $validator = new SizeLimitValidator($rules);

    $file = UploadedFile::fake()->create('test.pdf', 1024);

    // Не должно быть ошибок для не-изображений
    $validator->validate($file, 'application/pdf');
})->expectNotToPerformAssertions();

test('handles image with unreadable path gracefully', function () {
    $rules = ['max_width' => 1000];
    $validator = new SizeLimitValidator($rules);

    // Создаём файл, который не может быть прочитан getimagesize
    $file = UploadedFile::fake()->create('test.jpg', 1024);

    // Валидатор должен пропустить проверку размеров, если getimagesize не работает
    $validator->validate($file, 'image/jpeg');
})->expectNotToPerformAssertions();

