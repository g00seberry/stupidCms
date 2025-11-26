<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\FieldDefinition;
use Tests\TestCase;

uses(TestCase::class);

test('can create field definition with all properties', function () {
    $definition = new FieldDefinition(
        path: 'title',
        dataType: 'string',
        isRequired: true,
        cardinality: 'one',
        validationRules: ['min' => 1, 'max' => 500]
    );

    expect($definition->path)->toBe('title')
        ->and($definition->dataType)->toBe('string')
        ->and($definition->isRequired)->toBeTrue()
        ->and($definition->cardinality)->toBe('one')
        ->and($definition->validationRules)->toBe(['min' => 1, 'max' => 500]);
});

test('can check if field is array', function () {
    $arrayField = new FieldDefinition('tags', 'string', true, 'many');
    $singleField = new FieldDefinition('title', 'string', true, 'one');

    expect($arrayField->isArray())->toBeTrue()
        ->and($singleField->isArray())->toBeFalse();
});

test('can check if field is single', function () {
    $arrayField = new FieldDefinition('tags', 'string', true, 'many');
    $singleField = new FieldDefinition('title', 'string', true, 'one');

    expect($arrayField->isSingle())->toBeFalse()
        ->and($singleField->isSingle())->toBeTrue();
});

test('can check if field has validation rules', function () {
    $withRules = new FieldDefinition('title', 'string', true, 'one', ['min' => 1]);
    $withoutRules = new FieldDefinition('title', 'string', true, 'one', null);
    $withEmptyRules = new FieldDefinition('title', 'string', true, 'one', []);

    expect($withRules->hasValidationRules())->toBeTrue()
        ->and($withoutRules->hasValidationRules())->toBeFalse()
        ->and($withEmptyRules->hasValidationRules())->toBeFalse();
});

test('handles nested paths', function () {
    $definition = new FieldDefinition(
        path: 'author.name',
        dataType: 'string',
        isRequired: true,
        cardinality: 'one'
    );

    expect($definition->path)->toBe('author.name');
});

