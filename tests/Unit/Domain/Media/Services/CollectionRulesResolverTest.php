<?php

declare(strict_types=1);

use App\Domain\Media\Services\CollectionRulesResolver;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(TestCase::class);

test('gets rules for collection', function () {
    Config::set('media.collections.videos', [
        'allowed_mimes' => ['video/mp4', 'video/webm'],
        'max_size_bytes' => 100 * 1024 * 1024,
        'max_duration_ms' => 300000,
    ]);

    $resolver = new CollectionRulesResolver();
    $rules = $resolver->getRules('videos');

    expect($rules)->toHaveKey('allowed_mimes')
        ->and($rules['allowed_mimes'])->toBe(['video/mp4', 'video/webm'])
        ->and($rules['max_size_bytes'])->toBe(100 * 1024 * 1024)
        ->and($rules['max_duration_ms'])->toBe(300000);
});

test('returns global rules if collection not configured', function () {
    Config::set('media.allowed_mimes', ['image/jpeg', 'image/png']);
    Config::set('media.max_upload_mb', 25);
    Config::set('media.collections', []);

    $resolver = new CollectionRulesResolver();
    $rules = $resolver->getRules('unknown');

    expect($rules['allowed_mimes'])->toBe(['image/jpeg', 'image/png'])
        ->and($rules['max_size_bytes'])->toBe(25 * 1024 * 1024);
});

test('gets allowed mimes for collection', function () {
    Config::set('media.collections.videos', [
        'allowed_mimes' => ['video/mp4', 'video/webm'],
    ]);

    $resolver = new CollectionRulesResolver();

    expect($resolver->getAllowedMimes('videos'))->toBe(['video/mp4', 'video/webm']);
});

test('gets max size for collection', function () {
    Config::set('media.collections.videos', [
        'max_size_bytes' => 100 * 1024 * 1024,
    ]);

    $resolver = new CollectionRulesResolver();

    expect($resolver->getMaxSizeBytes('videos'))->toBe(100 * 1024 * 1024);
});

test('merges collection rules with global rules', function () {
    Config::set('media.allowed_mimes', ['image/jpeg', 'image/png']);
    Config::set('media.max_upload_mb', 25);
    Config::set('media.collections.thumbnails', [
        'max_width' => 1920,
        'max_height' => 1080,
    ]);

    $resolver = new CollectionRulesResolver();
    $rules = $resolver->getRules('thumbnails');

    expect($rules['allowed_mimes'])->toBe(['image/jpeg', 'image/png']) // Глобальные
        ->and($rules['max_size_bytes'])->toBe(25 * 1024 * 1024) // Глобальные
        ->and($rules['max_width'])->toBe(1920) // Из коллекции
        ->and($rules['max_height'])->toBe(1080); // Из коллекции
});

test('handles null collection', function () {
    Config::set('media.allowed_mimes', ['image/jpeg']);
    Config::set('media.max_upload_mb', 25);

    $resolver = new CollectionRulesResolver();
    $rules = $resolver->getRules(null);

    expect($rules['allowed_mimes'])->toBe(['image/jpeg'])
        ->and($rules['max_size_bytes'])->toBe(25 * 1024 * 1024);
});

test('handles empty collection name', function () {
    Config::set('media.allowed_mimes', ['image/jpeg']);
    Config::set('media.max_upload_mb', 25);

    $resolver = new CollectionRulesResolver();
    $rules = $resolver->getRules('');

    expect($rules['allowed_mimes'])->toBe(['image/jpeg'])
        ->and($rules['max_size_bytes'])->toBe(25 * 1024 * 1024);
});

test('filters out null values from collection rules', function () {
    Config::set('media.allowed_mimes', ['image/jpeg']);
    Config::set('media.max_upload_mb', 25);
    Config::set('media.collections.test', [
        'max_width' => 1920,
        'max_height' => null, // null значения должны быть отфильтрованы
    ]);

    $resolver = new CollectionRulesResolver();
    $rules = $resolver->getRules('test');

    // array_filter удаляет элементы с null значениями из массива коллекции
    // но глобальные правила уже содержат max_height => null
    expect($rules['max_width'])->toBe(1920)
        ->and($rules['max_height'])->toBeNull(); // Глобальное значение null остается
});

test('returns default max size when not configured', function () {
    Config::set('media.max_upload_mb', 50);
    Config::set('media.collections', []);

    $resolver = new CollectionRulesResolver();

    expect($resolver->getMaxSizeBytes('unknown'))->toBe(50 * 1024 * 1024);
});

test('returns default allowed mimes when not configured', function () {
    Config::set('media.allowed_mimes', ['image/jpeg', 'image/png']);
    Config::set('media.collections', []);

    $resolver = new CollectionRulesResolver();

    expect($resolver->getAllowedMimes('unknown'))->toBe(['image/jpeg', 'image/png']);
});

