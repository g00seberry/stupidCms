<?php

declare(strict_types=1);

use App\Rules\JsonValue;
use Illuminate\Support\Facades\Validator;

test('passes for valid json encodable value', function () {
    $rule = new JsonValue();
    
    $validator = Validator::make(
        ['data' => ['key' => 'value']],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes for string value', function () {
    $rule = new JsonValue();
    
    $validator = Validator::make(
        ['data' => 'simple string'],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes for array value', function () {
    $rule = new JsonValue();
    
    $validator = Validator::make(
        ['data' => ['item1', 'item2', 'item3']],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes for null value', function () {
    $rule = new JsonValue();
    
    $validator = Validator::make(
        ['data' => null],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes for numeric value', function () {
    $rule = new JsonValue();
    
    $validator = Validator::make(
        ['data' => 42],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes for boolean value', function () {
    $rule = new JsonValue();
    
    $validator = Validator::make(
        ['data' => true],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails when encoded size exceeds limit', function () {
    $rule = new JsonValue(maxBytes: 100);
    
    $largeData = str_repeat('a', 200);
    
    $validator = Validator::make(
        ['data' => $largeData],
        ['data' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
    // Message key is "validation.json_value_too_large"
});

test('passes when encoded size is within limit', function () {
    $rule = new JsonValue(maxBytes: 1000);
    
    $smallData = ['key' => 'value'];
    
    $validator = Validator::make(
        ['data' => $smallData],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('handles default max size', function () {
    $rule = new JsonValue(); // Default 65536 bytes
    
    $moderateData = str_repeat('x', 60000);
    
    $validator = Validator::make(
        ['data' => $moderateData],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes when max size is zero (disabled limit)', function () {
    // maxBytes = 0 means no limit (as per code: if ($this->maxBytes > 0 && ...))
    $rule = new JsonValue(maxBytes: 0);
    
    $validator = Validator::make(
        ['data' => 'any value'],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('handles nested objects', function () {
    $rule = new JsonValue();
    
    $nestedData = [
        'level1' => [
            'level2' => [
                'level3' => 'deep value',
            ],
        ],
    ];
    
    $validator = Validator::make(
        ['data' => $nestedData],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('handles unicode characters', function () {
    $rule = new JsonValue();
    
    $unicodeData = ['text' => 'Привет, мир! 你好世界 🌍'];
    
    $validator = Validator::make(
        ['data' => $unicodeData],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('validates exact size limit boundary', function () {
    $rule = new JsonValue(maxBytes: 50);
    
    // JSON encoded string adds quotes, so "a" becomes 3 bytes ("a" with quotes)
    // 48 'a's = 50 bytes with quotes
    $data = str_repeat('a', 48);
    
    $validator = Validator::make(
        ['data' => $data],
        ['data' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
    
    // Now exceed by 1 byte (51 bytes total)
    $dataExceeds = str_repeat('a', 49);
    $validatorFails = Validator::make(
        ['data' => $dataExceeds],
        ['data' => [$rule]]
    );
    
    expect($validatorFails->fails())->toBeTrue();
});

