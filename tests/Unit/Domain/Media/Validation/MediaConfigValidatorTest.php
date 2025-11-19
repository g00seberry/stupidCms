<?php

declare(strict_types=1);

use App\Domain\Media\Validation\MediaConfigValidator;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Unit-тесты для MediaConfigValidator.
 *
 * Тестирует валидацию конфигурации медиа-файлов:
 * - проверка наличия обязательных вариантов (thumbnail, medium, large)
 * - проверка структуры конфигурации вариантов
 * - проверка наличия обязательного ключа 'max' в каждом варианте
 */

uses(TestCase::class);

beforeEach(function () {
    // Сохраняем оригинальную конфигурацию
    $this->originalVariants = config('media.variants', []);
});

afterEach(function () {
    // Восстанавливаем оригинальную конфигурацию
    Config::set('media.variants', $this->originalVariants);
});

test('validates successfully with all required variants', function () {
    Config::set('media.variants', [
        'thumbnail' => ['max' => 320],
        'medium' => ['max' => 1024],
        'large' => ['max' => 2048],
    ]);

    $validator = new MediaConfigValidator();
    
    expect(fn () => $validator->validate())->not->toThrow(RuntimeException::class);
});

test('validates successfully with required variants and additional ones', function () {
    Config::set('media.variants', [
        'thumbnail' => ['max' => 320],
        'medium' => ['max' => 1024],
        'large' => ['max' => 2048],
        'extra' => ['max' => 4096],
    ]);

    $validator = new MediaConfigValidator();
    
    expect(fn () => $validator->validate())->not->toThrow(RuntimeException::class);
});

test('throws exception when thumbnail variant is missing', function () {
    Config::set('media.variants', [
        'medium' => ['max' => 1024],
        'large' => ['max' => 2048],
    ]);

    $validator = new MediaConfigValidator();
    
    expect(fn () => $validator->validate())
        ->toThrow(RuntimeException::class, 'Required media variants are missing in config: thumbnail');
});

test('throws exception when medium variant is missing', function () {
    Config::set('media.variants', [
        'thumbnail' => ['max' => 320],
        'large' => ['max' => 2048],
    ]);

    $validator = new MediaConfigValidator();
    
    expect(fn () => $validator->validate())
        ->toThrow(RuntimeException::class, 'Required media variants are missing in config: medium');
});

test('throws exception when large variant is missing', function () {
    Config::set('media.variants', [
        'thumbnail' => ['max' => 320],
        'medium' => ['max' => 1024],
    ]);

    $validator = new MediaConfigValidator();
    
    expect(fn () => $validator->validate())
        ->toThrow(RuntimeException::class, 'Required media variants are missing in config: large');
});

test('throws exception when multiple required variants are missing', function () {
    Config::set('media.variants', [
        'thumbnail' => ['max' => 320],
    ]);

    $validator = new MediaConfigValidator();
    
    expect(fn () => $validator->validate())
        ->toThrow(RuntimeException::class, 'Required media variants are missing in config: medium, large');
});

test('throws exception when all required variants are missing', function () {
    Config::set('media.variants', []);

    $validator = new MediaConfigValidator();
    
    expect(fn () => $validator->validate())
        ->toThrow(RuntimeException::class, 'Required media variants are missing in config: thumbnail, medium, large');
});

test('throws exception when variants config is not an array', function () {
    Config::set('media.variants', 'not-an-array');

    $validator = new MediaConfigValidator();
    
    expect(fn () => $validator->validate())
        ->toThrow(RuntimeException::class, 'Config key "media.variants" must be an array');
});

test('throws exception when variant config is not an array', function () {
    Config::set('media.variants', [
        'thumbnail' => 'not-an-array',
        'medium' => ['max' => 1024],
        'large' => ['max' => 2048],
    ]);

    $validator = new MediaConfigValidator();
    
    expect(fn () => $validator->validate())
        ->toThrow(RuntimeException::class, 'Config key "media.variants.thumbnail" must be an array');
});

test('throws exception when variant max key is missing', function () {
    Config::set('media.variants', [
        'thumbnail' => ['format' => 'webp'],
        'medium' => ['max' => 1024],
        'large' => ['max' => 2048],
    ]);

    $validator = new MediaConfigValidator();
    
    expect(fn () => $validator->validate())
        ->toThrow(RuntimeException::class, 'Config key "media.variants.thumbnail.max" is required');
});

test('validates successfully with optional format and quality keys', function () {
    Config::set('media.variants', [
        'thumbnail' => ['max' => 320, 'format' => 'webp', 'quality' => 90],
        'medium' => ['max' => 1024, 'format' => 'jpg'],
        'large' => ['max' => 2048, 'quality' => 85],
    ]);

    $validator = new MediaConfigValidator();
    
    expect(fn () => $validator->validate())->not->toThrow(RuntimeException::class);
});

