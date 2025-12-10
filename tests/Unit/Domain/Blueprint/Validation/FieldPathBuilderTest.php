<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\FieldPathBuilder;
use App\Domain\Blueprint\Validation\ValidationConstants;
use App\Domain\Blueprint\Validation\Rules\DistinctRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;

beforeEach(function () {
    $this->builder = new FieldPathBuilder();
});

// 2.1. Простые пути

test('buildFieldPath adds data_json prefix to simple path', function () {
    $result = $this->builder->buildFieldPath('title', []);

    expect($result)->toBe('data_json.title');
});

test('buildFieldPath handles empty path', function () {
    $result = $this->builder->buildFieldPath('', []);

    expect($result)->toBe('data_json.');
});

// 2.2. Вложенные пути

test('buildFieldPath handles single level nesting', function () {
    $result = $this->builder->buildFieldPath('author.name', []);

    expect($result)->toBe('data_json.author.name');
});

test('buildFieldPath handles multi level nesting', function () {
    $result = $this->builder->buildFieldPath('author.contacts.phone', []);

    expect($result)->toBe('data_json.author.contacts.phone');
});

test('buildFieldPath handles deep nesting', function () {
    $result = $this->builder->buildFieldPath('level1.level2.level3.level4.level5.field', []);

    expect($result)->toBe('data_json.level1.level2.level3.level4.level5.field');
});

// 2.3. Обработка cardinality

test('buildFieldPath replaces segment with wildcard if parent has cardinality many', function () {
    $pathCardinalities = [
        'author' => ValidationConstants::CARDINALITY_MANY,
    ];

    $result = $this->builder->buildFieldPath('author.contacts', $pathCardinalities);

    // Если 'author' имеет cardinality='many', то сегмент 'contacts' заменяется на '*.contacts'
    // Но сам 'author' остается как есть
    expect($result)->toBe('data_json.author.*.contacts');
});

test('buildFieldPath handles multiple array levels', function () {
    $pathCardinalities = [
        'items' => ValidationConstants::CARDINALITY_MANY,
        'items.tags' => ValidationConstants::CARDINALITY_MANY,
    ];

    $result = $this->builder->buildFieldPath('items.tags.name', $pathCardinalities);

    // items имеет cardinality='many', поэтому tags заменяется на *.tags
    // items.tags имеет cardinality='many', поэтому name заменяется на *.name
    expect($result)->toBe('data_json.items.*.tags.*.name');
});

test('buildFieldPath does not replace segment if parent has cardinality one', function () {
    $pathCardinalities = [
        'author' => ValidationConstants::CARDINALITY_ONE,
    ];

    $result = $this->builder->buildFieldPath('author.contacts', $pathCardinalities);

    expect($result)->toBe('data_json.author.contacts');
});

test('buildFieldPath handles first segment correctly', function () {
    $pathCardinalities = [];

    $result = $this->builder->buildFieldPath('title', $pathCardinalities);

    expect($result)->toBe('data_json.title');
});

test('buildFieldPath handles nested arrays correctly', function () {
    $pathCardinalities = [
        'author' => ValidationConstants::CARDINALITY_MANY,
        'author.contacts' => ValidationConstants::CARDINALITY_MANY,
    ];

    $result = $this->builder->buildFieldPath('author.contacts.phone', $pathCardinalities);

    // author имеет cardinality='many', поэтому contacts заменяется на *.contacts
    // author.contacts имеет cardinality='many', поэтому phone заменяется на *.phone
    expect($result)->toBe('data_json.author.*.contacts.*.phone');
});

test('buildFieldPath handles mixed structure arrays and objects', function () {
    $pathCardinalities = [
        'events' => ValidationConstants::CARDINALITY_MANY,
    ];

    $result = $this->builder->buildFieldPath('events.venue.location.coordinates.lat', $pathCardinalities);

    // events имеет cardinality='many', поэтому venue заменяется на *.venue
    // остальные сегменты - объекты, остаются как есть
    expect($result)->toBe('data_json.events.*.venue.location.coordinates.lat');
});

// 2.4. Граничные случаи

test('buildFieldPath handles path with maximum nesting', function () {
    $deepPath = implode('.', array_fill(0, 20, 'level'));
    $result = $this->builder->buildFieldPath($deepPath, []);

    expect($result)->toStartWith('data_json.');
    expect($result)->toContain('level');
});

test('buildFieldPath works with custom prefix', function () {
    $result = $this->builder->buildFieldPath('title', [], 'custom.');

    expect($result)->toBe('custom.title');
});

test('buildFieldPath handles complex nested arrays', function () {
    $pathCardinalities = [
        'products' => ValidationConstants::CARDINALITY_MANY,
        'products.variants' => ValidationConstants::CARDINALITY_MANY,
        'products.variants.options' => ValidationConstants::CARDINALITY_MANY,
    ];

    $result = $this->builder->buildFieldPath('products.variants.options.name', $pathCardinalities);

    // products имеет cardinality='many', поэтому variants заменяется на *.variants
    // products.variants имеет cardinality='many', поэтому options заменяется на *.options
    // products.variants.options имеет cardinality='many', поэтому name заменяется на *.name
    expect($result)->toBe('data_json.products.*.variants.*.options.*.name');
});

test('buildFieldPath handles alternating arrays and objects', function () {
    $pathCardinalities = [
        'sections' => ValidationConstants::CARDINALITY_MANY,
        'sections.blocks' => ValidationConstants::CARDINALITY_MANY,
    ];

    $result = $this->builder->buildFieldPath('sections.blocks.content', $pathCardinalities);

    // sections имеет cardinality='many', поэтому blocks заменяется на *.blocks
    // sections.blocks имеет cardinality='many', поэтому content заменяется на *.content
    expect($result)->toBe('data_json.sections.*.blocks.*.content');
});

// 2.5. Обработка правил валидации
// Примечание: Метод buildFieldPathForRule() был удалён в процессе рефакторинга.
// Все правила теперь обрабатываются через buildFieldPath() с учётом cardinality.

