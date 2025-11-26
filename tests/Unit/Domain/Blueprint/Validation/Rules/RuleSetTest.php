<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\PatternRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use App\Domain\Blueprint\Validation\Rules\RuleSet;
use Tests\TestCase;

uses(TestCase::class);

test('can add and retrieve rules for a field', function () {
    $ruleSet = new RuleSet();
    $minRule = new MinRule(1, 'string');
    $maxRule = new MaxRule(500, 'string');

    $ruleSet->addRule('content_json.title', $minRule);
    $ruleSet->addRule('content_json.title', $maxRule);

    $rules = $ruleSet->getRulesForField('content_json.title');

    expect($rules)->toHaveCount(2)
        ->and($rules[0])->toBeInstanceOf(MinRule::class)
        ->and($rules[1])->toBeInstanceOf(MaxRule::class);
});

test('returns empty array for field without rules', function () {
    $ruleSet = new RuleSet();

    $rules = $ruleSet->getRulesForField('content_json.nonexistent');

    expect($rules)->toBeArray()
        ->and($rules)->toBeEmpty();
});

test('can get all rules', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.title', new RequiredRule());
    $ruleSet->addRule('content_json.phone', new PatternRule('^\\+?[1-9]\\d{1,14}$'));

    $allRules = $ruleSet->getAllRules();

    expect($allRules)->toHaveCount(2)
        ->and($allRules)->toHaveKey('content_json.title')
        ->and($allRules)->toHaveKey('content_json.phone')
        ->and($allRules['content_json.title'])->toHaveCount(1)
        ->and($allRules['content_json.phone'])->toHaveCount(1);
});

test('can check if field has rules', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.title', new RequiredRule());

    expect($ruleSet->hasRulesForField('content_json.title'))->toBeTrue()
        ->and($ruleSet->hasRulesForField('content_json.nonexistent'))->toBeFalse();
});

test('can get all field paths', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.title', new RequiredRule());
    $ruleSet->addRule('content_json.phone', new PatternRule('^\\+?[1-9]\\d{1,14}$'));
    $ruleSet->addRule('content_json.author.name', new RequiredRule());

    $paths = $ruleSet->getFieldPaths();

    expect($paths)->toHaveCount(3)
        ->and($paths)->toContain('content_json.title')
        ->and($paths)->toContain('content_json.phone')
        ->and($paths)->toContain('content_json.author.name');
});

test('can check if rule set is empty', function () {
    $emptyRuleSet = new RuleSet();
    $nonEmptyRuleSet = new RuleSet();
    $nonEmptyRuleSet->addRule('content_json.title', new RequiredRule());

    expect($emptyRuleSet->isEmpty())->toBeTrue()
        ->and($nonEmptyRuleSet->isEmpty())->toBeFalse();
});

test('can add multiple rules to same field', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('content_json.title', new RequiredRule());
    $ruleSet->addRule('content_json.title', new MinRule(1, 'string'));
    $ruleSet->addRule('content_json.title', new MaxRule(500, 'string'));

    $rules = $ruleSet->getRulesForField('content_json.title');

    expect($rules)->toHaveCount(3);
});

