<?php

declare(strict_types=1);

use App\Rules\DistinctObjects;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

test('passes for array with unique simple values', function () {
    $rule = new DistinctObjects();
    
    $validator = Validator::make(
        ['tags' => ['tag1', 'tag2', 'tag3']],
        ['tags' => ['array', $rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails for array with duplicate simple values', function () {
    $rule = new DistinctObjects();
    
    $validator = Validator::make(
        ['tags' => ['tag1', 'tag2', 'tag1']],
        ['tags' => ['array', $rule]]
    );

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->has('tags'))->toBeTrue();
});

test('passes for array with unique objects', function () {
    $rule = new DistinctObjects();
    
    $validator = Validator::make(
        [
            'author' => [
                ['name' => 'John', 'email' => 'john@example.com'],
                ['name' => 'Jane', 'email' => 'jane@example.com'],
            ],
        ],
        ['author' => ['array', $rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails for array with duplicate objects', function () {
    $rule = new DistinctObjects();
    
    $validator = Validator::make(
        [
            'author' => [
                ['name' => '1', 'email' => '1'],
                ['name' => '1', 'email' => '1'],
            ],
        ],
        ['author' => ['array', $rule]]
    );

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->has('author'))->toBeTrue();
});

test('fails for array with duplicate objects even if order of keys differs', function () {
    $rule = new DistinctObjects();
    
    $validator = Validator::make(
        [
            'author' => [
                ['name' => 'John', 'email' => 'john@example.com'],
                ['email' => 'john@example.com', 'name' => 'John'], // Те же значения, но другой порядок ключей
            ],
        ],
        ['author' => ['array', $rule]]
    );

    // Должно пройти, так как мы нормализуем объекты (сортируем ключи)
    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->has('author'))->toBeTrue();
});

test('passes for array with objects that have same values but different keys', function () {
    $rule = new DistinctObjects();
    
    $validator = Validator::make(
        [
            'author' => [
                ['name' => 'John', 'email' => 'john@example.com'],
                ['name' => 'John', 'email' => 'jane@example.com'], // Разные email
            ],
        ],
        ['author' => ['array', $rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes for empty array', function () {
    $rule = new DistinctObjects();
    
    $validator = Validator::make(
        ['tags' => []],
        ['tags' => ['array', $rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes for non-array value', function () {
    $rule = new DistinctObjects();
    
    $validator = Validator::make(
        ['tags' => 'not-an-array'],
        ['tags' => [$rule]]
    );

    // Правило должно пропустить не-массивы (должны обрабатываться другими правилами)
    expect($validator->passes())->toBeTrue();
});

test('passes for array with nested objects', function () {
    $rule = new DistinctObjects();
    
    $validator = Validator::make(
        [
            'items' => [
                ['id' => 1, 'meta' => ['type' => 'A']],
                ['id' => 2, 'meta' => ['type' => 'B']],
            ],
        ],
        ['items' => ['array', $rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails for array with duplicate nested objects', function () {
    $rule = new DistinctObjects();
    
    $validator = Validator::make(
        [
            'items' => [
                ['id' => 1, 'meta' => ['type' => 'A']],
                ['id' => 1, 'meta' => ['type' => 'A']],
            ],
        ],
        ['items' => ['array', $rule]]
    );

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->has('items'))->toBeTrue();
});

