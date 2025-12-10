<?php

declare(strict_types=1);

use App\Rules\FieldComparison;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

test('passes when value is greater than or equal to other field', function () {
    $rule = new FieldComparison('>=', 'data_json.start_date');

    $validator = Validator::make(
        [
            'end_date' => '2024-01-15',
            'data_json' => [
                'start_date' => '2024-01-01',
            ],
        ],
        ['end_date' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails when value is less than other field', function () {
    $rule = new FieldComparison('>=', 'data_json.start_date');

    $validator = Validator::make(
        [
            'end_date' => '2024-01-01',
            'data_json' => [
                'start_date' => '2024-01-15',
            ],
        ],
        ['end_date' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('passes when value equals other field', function () {
    $rule = new FieldComparison('==', 'data_json.start_date');

    $validator = Validator::make(
        [
            'end_date' => '2024-01-01',
            'data_json' => [
                'start_date' => '2024-01-01',
            ],
        ],
        ['end_date' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes when value is not equal to other field', function () {
    $rule = new FieldComparison('!=', 'data_json.start_date');

    $validator = Validator::make(
        [
            'end_date' => '2024-01-15',
            'data_json' => [
                'start_date' => '2024-01-01',
            ],
        ],
        ['end_date' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes when comparing with constant value', function () {
    $rule = new FieldComparison('>=', '', 100);

    $validator = Validator::make(
        ['price' => 150],
        ['price' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails when value is less than constant', function () {
    $rule = new FieldComparison('>=', '', 100);

    $validator = Validator::make(
        ['price' => 50],
        ['price' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('skips validation when other field is missing', function () {
    $rule = new FieldComparison('>=', 'data_json.start_date');

    $validator = Validator::make(
        [
            'end_date' => '2024-01-15',
            'data_json' => [],
        ],
        ['end_date' => [$rule]]
    );

    // Валидация пропускается, если сравниваемое поле отсутствует
    expect($validator->passes())->toBeTrue();
});

test('skips validation when current value is null', function () {
    $rule = new FieldComparison('>=', 'data_json.start_date');

    $validator = Validator::make(
        [
            'end_date' => null,
            'data_json' => [
                'start_date' => '2024-01-01',
            ],
        ],
        ['end_date' => [$rule]]
    );

    // Валидация пропускается для null значений (required/nullable обработают это)
    expect($validator->passes())->toBeTrue();
});

test('handles numeric comparison correctly', function () {
    $rule = new FieldComparison('>', 'data_json.min_price');

    $validator = Validator::make(
        [
            'price' => 150,
            'data_json' => [
                'min_price' => 100,
            ],
        ],
        ['price' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('handles date string comparison correctly', function () {
    $rule = new FieldComparison('>=', 'data_json.start_date');

    $validator = Validator::make(
        [
            'end_date' => '2024-01-15',
            'data_json' => [
                'start_date' => '2024-01-01',
            ],
        ],
        ['end_date' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('handles less than operator', function () {
    $rule = new FieldComparison('<', 'data_json.max_price');

    $validator = Validator::make(
        [
            'price' => 150,
            'data_json' => [
                'max_price' => 200,
            ],
        ],
        ['price' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('handles less than or equal operator', function () {
    $rule = new FieldComparison('<=', 'data_json.max_price');

    $validator = Validator::make(
        [
            'price' => 200,
            'data_json' => [
                'max_price' => 200,
            ],
        ],
        ['price' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('handles nested field paths', function () {
    $rule = new FieldComparison('>=', 'data_json.dates.start');

    $validator = Validator::make(
        [
            'end_date' => '2024-01-15',
            'data_json' => [
                'dates' => [
                    'start' => '2024-01-01',
                ],
            ],
        ],
        ['end_date' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

