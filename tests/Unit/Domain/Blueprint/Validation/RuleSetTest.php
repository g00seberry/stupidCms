<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use App\Domain\Blueprint\Validation\Rules\RuleSet;

/**
 * Unit-тесты для RuleSet.
 *
 * Тестирует:
 * - Добавление правил для полей
 * - Получение правил для полей
 * - Утилиты (getFieldPaths, isEmpty, hasRulesForField)
 */

beforeEach(function () {
    $this->ruleSet = new RuleSet();
});

// 4.1. Добавление правил

test('addRule adds rule for new field', function () {
    $rule = new RequiredRule();
    $this->ruleSet->addRule('data_json.title', $rule);

    expect($this->ruleSet->hasRulesForField('data_json.title'))->toBeTrue();
    expect($this->ruleSet->getRulesForField('data_json.title'))->toHaveCount(1);
    expect($this->ruleSet->getRulesForField('data_json.title')[0])->toBe($rule);
});

test('addRule adds multiple rules for one field', function () {
    $requiredRule = new RequiredRule();
    $minRule = new MinRule(5);
    $maxRule = new MaxRule(100);

    $this->ruleSet->addRule('data_json.title', $requiredRule);
    $this->ruleSet->addRule('data_json.title', $minRule);
    $this->ruleSet->addRule('data_json.title', $maxRule);

    $rules = $this->ruleSet->getRulesForField('data_json.title');
    expect($rules)->toHaveCount(3);
    expect($rules[0])->toBe($requiredRule);
    expect($rules[1])->toBe($minRule);
    expect($rules[2])->toBe($maxRule);
});

test('addRule adds rules for different fields', function () {
    $titleRule = new RequiredRule();
    $descriptionRule = new MinRule(10);

    $this->ruleSet->addRule('data_json.title', $titleRule);
    $this->ruleSet->addRule('data_json.description', $descriptionRule);

    expect($this->ruleSet->hasRulesForField('data_json.title'))->toBeTrue();
    expect($this->ruleSet->hasRulesForField('data_json.description'))->toBeTrue();
    expect($this->ruleSet->getRulesForField('data_json.title'))->toHaveCount(1);
    expect($this->ruleSet->getRulesForField('data_json.description'))->toHaveCount(1);
});

// 4.2. Получение правил

test('getRulesForField returns rules for existing field', function () {
    $rule1 = new RequiredRule();
    $rule2 = new MinRule(5);

    $this->ruleSet->addRule('data_json.title', $rule1);
    $this->ruleSet->addRule('data_json.title', $rule2);

    $rules = $this->ruleSet->getRulesForField('data_json.title');
    expect($rules)->toHaveCount(2);
    expect($rules)->toContain($rule1);
    expect($rules)->toContain($rule2);
});

test('getRulesForField returns empty array for non-existent field', function () {
    $rules = $this->ruleSet->getRulesForField('data_json.non_existent');
    expect($rules)->toBeArray();
    expect($rules)->toBeEmpty();
});

test('getAllRules returns all rules', function () {
    $titleRule = new RequiredRule();
    $descriptionRule = new MinRule(10);
    $authorRule = new MaxRule(100);

    $this->ruleSet->addRule('data_json.title', $titleRule);
    $this->ruleSet->addRule('data_json.description', $descriptionRule);
    $this->ruleSet->addRule('data_json.author', $authorRule);

    $allRules = $this->ruleSet->getAllRules();

    expect($allRules)->toBeArray();
    expect($allRules)->toHaveKeys(['data_json.title', 'data_json.description', 'data_json.author']);
    expect($allRules['data_json.title'])->toHaveCount(1);
    expect($allRules['data_json.description'])->toHaveCount(1);
    expect($allRules['data_json.author'])->toHaveCount(1);
});

test('hasRulesForField returns true for field with rules', function () {
    $this->ruleSet->addRule('data_json.title', new RequiredRule());

    expect($this->ruleSet->hasRulesForField('data_json.title'))->toBeTrue();
});

test('hasRulesForField returns false for field without rules', function () {
    expect($this->ruleSet->hasRulesForField('data_json.title'))->toBeFalse();
});

// 4.3. Утилиты

test('getFieldPaths returns list of all field paths', function () {
    $this->ruleSet->addRule('data_json.title', new RequiredRule());
    $this->ruleSet->addRule('data_json.description', new MinRule(10));
    $this->ruleSet->addRule('data_json.author.name', new MaxRule(100));

    $paths = $this->ruleSet->getFieldPaths();

    expect($paths)->toBeArray();
    expect($paths)->toHaveCount(3);
    expect($paths)->toContain('data_json.title');
    expect($paths)->toContain('data_json.description');
    expect($paths)->toContain('data_json.author.name');
});

test('getFieldPaths returns empty array for empty RuleSet', function () {
    $paths = $this->ruleSet->getFieldPaths();
    expect($paths)->toBeArray();
    expect($paths)->toBeEmpty();
});

test('isEmpty returns true for empty RuleSet', function () {
    expect($this->ruleSet->isEmpty())->toBeTrue();
});

test('isEmpty returns false for RuleSet with rules', function () {
    $this->ruleSet->addRule('data_json.title', new RequiredRule());
    expect($this->ruleSet->isEmpty())->toBeFalse();
});

