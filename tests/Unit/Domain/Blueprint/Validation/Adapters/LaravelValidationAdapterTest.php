<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Adapters;

use App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapter;
use App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface;
use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\NullableRule;
use App\Domain\Blueprint\Validation\Rules\PatternRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use App\Domain\Blueprint\Validation\Rules\RuleSet;
use Tests\TestCase;

uses(TestCase::class);

test('adapts required rule correctly', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.title', new RequiredRule());

    $result = $adapter->adapt($ruleSet, ['content_json.title' => 'string']);

    expect($result)->toHaveKey('content_json.title')
        ->and($result['content_json.title'])->toContain('required')
        ->and($result['content_json.title'])->toContain('string');
});

test('adapts nullable rule correctly', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.phone', new NullableRule());

    $result = $adapter->adapt($ruleSet, ['content_json.phone' => 'string']);

    expect($result)->toHaveKey('content_json.phone')
        ->and($result['content_json.phone'])->toContain('nullable')
        ->and($result['content_json.phone'])->toContain('string');
});

test('adapts min rule correctly', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.title', new RequiredRule());
    $ruleSet->addRule('content_json.title', new MinRule(1, 'string'));

    $result = $adapter->adapt($ruleSet, ['content_json.title' => 'string']);

    expect($result['content_json.title'])->toContain('required')
        ->and($result['content_json.title'])->toContain('string')
        ->and($result['content_json.title'])->toContain('min:1');
});

test('adapts max rule correctly', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.title', new RequiredRule());
    $ruleSet->addRule('content_json.title', new MaxRule(500, 'string'));

    $result = $adapter->adapt($ruleSet, ['content_json.title' => 'string']);

    expect($result['content_json.title'])->toContain('required')
        ->and($result['content_json.title'])->toContain('string')
        ->and($result['content_json.title'])->toContain('max:500');
});

test('adapts pattern rule correctly', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.phone', new NullableRule());
    $ruleSet->addRule('content_json.phone', new PatternRule('^\\+?[1-9]\\d{1,14}$'));

    $result = $adapter->adapt($ruleSet, ['content_json.phone' => 'string']);

    expect($result['content_json.phone'])->toContain('nullable')
        ->and($result['content_json.phone'])->toContain('string')
        ->and($result['content_json.phone'])->toContain('regex:/^\\+?[1-9]\\d{1,14}$/');
});

test('adapts pattern rule with delimiters correctly', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.test', new PatternRule('/^test$/i'));

    $result = $adapter->adapt($ruleSet, ['content_json.test' => 'string']);

    expect($result['content_json.test'])->toContain('regex:/^test$/i');
});

test('adapts float min max with precision', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.price', new RequiredRule());
    $ruleSet->addRule('content_json.price', new MinRule(0.5, 'float'));
    $ruleSet->addRule('content_json.price', new MaxRule(100.99, 'float'));

    $result = $adapter->adapt($ruleSet, ['content_json.price' => 'float']);

    expect($result['content_json.price'])->toContain('required')
        ->and($result['content_json.price'])->toContain('numeric')
        ->and($result['content_json.price'])->toContain('min:0.5')
        ->and($result['content_json.price'])->toContain('max:100.99');
});

test('adapts multiple rules for same field', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.title', new RequiredRule());
    $ruleSet->addRule('content_json.title', new MinRule(1, 'string'));
    $ruleSet->addRule('content_json.title', new MaxRule(500, 'string'));
    $ruleSet->addRule('content_json.title', new PatternRule('^[A-Za-z0-9]+$'));

    $result = $adapter->adapt($ruleSet, ['content_json.title' => 'string']);

    expect($result['content_json.title'])->toHaveCount(5)
        ->and($result['content_json.title'])->toContain('required')
        ->and($result['content_json.title'])->toContain('string')
        ->and($result['content_json.title'])->toContain('min:1')
        ->and($result['content_json.title'])->toContain('max:500')
        ->and($result['content_json.title'])->toContain('regex:/^[A-Za-z0-9]+$/');
});

test('adapts rules for multiple fields', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.title', new RequiredRule());
    $ruleSet->addRule('content_json.phone', new NullableRule());
    $ruleSet->addRule('content_json.count', new RequiredRule());

    $result = $adapter->adapt($ruleSet, [
        'content_json.title' => 'string',
        'content_json.phone' => 'string',
        'content_json.count' => 'int',
    ]);

    expect($result)->toHaveCount(3)
        ->and($result)->toHaveKey('content_json.title')
        ->and($result)->toHaveKey('content_json.phone')
        ->and($result)->toHaveKey('content_json.count')
        ->and($result['content_json.title'])->toContain('string')
        ->and($result['content_json.phone'])->toContain('string')
        ->and($result['content_json.count'])->toContain('integer');
});

test('inserts base type after required nullable', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.title', new RequiredRule());
    $ruleSet->addRule('content_json.title', new MinRule(1, 'string'));

    $result = $adapter->adapt($ruleSet, ['content_json.title' => 'string']);

    // Порядок: required, string, min:1
    expect($result['content_json.title'][0])->toBe('required')
        ->and($result['content_json.title'][1])->toBe('string')
        ->and($result['content_json.title'][2])->toBe('min:1');
});

test('handles empty rule set', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();

    $result = $adapter->adapt($ruleSet);

    expect($result)->toBeEmpty();
});

test('handles field without data type', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.title', new RequiredRule());

    $result = $adapter->adapt($ruleSet);

    expect($result)->toHaveKey('content_json.title')
        ->and($result['content_json.title'])->toContain('required')
        ->and($result['content_json.title'])->not->toContain('string');
});

test('adapts different data types correctly', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.text', new RequiredRule());
    $ruleSet->addRule('content_json.number', new RequiredRule());
    $ruleSet->addRule('content_json.price', new RequiredRule());
    $ruleSet->addRule('content_json.active', new RequiredRule());
    $ruleSet->addRule('content_json.date', new RequiredRule());

    $result = $adapter->adapt($ruleSet, [
        'content_json.text' => 'text',
        'content_json.number' => 'int',
        'content_json.price' => 'float',
        'content_json.active' => 'bool',
        'content_json.date' => 'date',
    ]);

    expect($result['content_json.text'])->toContain('string')
        ->and($result['content_json.number'])->toContain('integer')
        ->and($result['content_json.price'])->toContain('numeric')
        ->and($result['content_json.active'])->toContain('boolean')
        ->and($result['content_json.date'])->toContain('date');
});

test('adapts array field correctly', function () {
    $adapter = app(LaravelValidationAdapterInterface::class);
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.tags.*', new MinRule(1, 'string'));
    $ruleSet->addRule('content_json.tags.*', new MaxRule(50, 'string'));

    $result = $adapter->adapt($ruleSet, ['content_json.tags.*' => 'string']);

    expect($result)->toHaveKey('content_json.tags.*')
        ->and($result['content_json.tags.*'])->toContain('string')
        ->and($result['content_json.tags.*'])->toContain('min:1')
        ->and($result['content_json.tags.*'])->toContain('max:50');
});

