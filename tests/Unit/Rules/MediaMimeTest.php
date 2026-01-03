<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Models\Media;
use App\Rules\MediaMime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('passes validation when media MIME type is in allowed list (single value)', function () {
    $media = Media::factory()->create(['mime' => 'image/jpeg']);
    $rule = new MediaMime(['image/jpeg', 'image/png'], 'avatar');
    
    $validator = Validator::make(
        ['avatar' => $media->id],
        ['avatar' => [$rule]]
    );
    
    expect($validator->passes())->toBeTrue();
});

test('fails validation when media MIME type is not in allowed list (single value)', function () {
    $media = Media::factory()->create(['mime' => 'video/mp4']);
    $rule = new MediaMime(['image/jpeg', 'image/png'], 'avatar');
    
    $validator = Validator::make(
        ['avatar' => $media->id],
        ['avatar' => [$rule]]
    );
    
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('avatar'))->toContain('MIME type in:');
});

test('passes validation when all media MIME types are in allowed list (array)', function () {
    $media1 = Media::factory()->create(['mime' => 'image/jpeg']);
    $media2 = Media::factory()->create(['mime' => 'image/png']);
    $rule = new MediaMime(['image/jpeg', 'image/png', 'image/gif'], 'avatars');
    
    $validator = Validator::make(
        ['avatars' => [$media1->id, $media2->id]],
        ['avatars' => ['array'], 'avatars.*' => [$rule]]
    );
    
    expect($validator->passes())->toBeTrue();
});

test('fails validation when any media MIME type is not in allowed list (array)', function () {
    $media1 = Media::factory()->create(['mime' => 'image/jpeg']);
    $media2 = Media::factory()->create(['mime' => 'video/mp4']);
    $rule = new MediaMime(['image/jpeg', 'image/png'], 'avatars');
    
    $validator = Validator::make(
        ['avatars' => [$media1->id, $media2->id]],
        ['avatars' => ['array'], 'avatars.*' => [$rule]]
    );
    
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('avatars.1'))->toBeTrue();
});

test('passes validation when value is null', function () {
    $rule = new MediaMime(['image/jpeg', 'image/png'], 'avatar');
    
    $validator = Validator::make(
        ['avatar' => null],
        ['avatar' => [$rule]]
    );
    
    expect($validator->passes())->toBeTrue();
});

test('passes validation when media does not exist (should be handled by exists rule)', function () {
    $rule = new MediaMime(['image/jpeg', 'image/png'], 'avatar');
    
    $validator = Validator::make(
        ['avatar' => '01HZ1234567890123456789012'],
        ['avatar' => [$rule]]
    );
    
    // Правило не должно падать, если медиа не существует (exists правило обработает это)
    expect($validator->passes())->toBeTrue();
});

test('skips validation for invalid value types', function () {
    $rule = new MediaMime(['image/jpeg'], 'avatar');
    
    $validator = Validator::make(
        ['avatar' => 12345], // Не строка
        ['avatar' => [$rule]]
    );
    
    // Правило должно пропустить валидацию для неподдерживаемых типов
    expect($validator->passes())->toBeTrue();
});

test('handles empty array gracefully', function () {
    $rule = new MediaMime(['image/jpeg'], 'avatars');
    
    $validator = Validator::make(
        ['avatars' => []],
        ['avatars' => ['array'], 'avatars.*' => [$rule]]
    );
    
    expect($validator->passes())->toBeTrue();
});

