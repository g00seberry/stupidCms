<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\EntryValidationService;
use App\Domain\Blueprint\Validation\EntryValidationServiceInterface;
use App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface;
use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\NullableRule;
use App\Domain\Blueprint\Validation\Rules\PatternRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Domain\Blueprint\Validation\Rules\RuleSet;
use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('builds rules for simple path', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'cardinality' => 'one',
        'validation_rules' => [
            'required' => true,
            'min' => 1,
            'max' => 500,
        ],
    ]);

    $service = app(EntryValidationServiceInterface::class);
    $ruleSet = $service->buildRulesFor($blueprint);

    expect($ruleSet->hasRulesForField('content_json.title'))->toBeTrue();
    
    $rules = $ruleSet->getRulesForField('content_json.title');
    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($rules[1])->toBeInstanceOf(MinRule::class)
        ->and($rules[2])->toBeInstanceOf(MaxRule::class);
});

test('builds rules for nullable path', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'phone',
        'full_path' => 'phone',
        'data_type' => 'string',
        'cardinality' => 'one',
        'validation_rules' => [
            'required' => false,
            'pattern' => '^\\+?[1-9]\\d{1,14}$',
        ],
    ]);

    $service = app(EntryValidationServiceInterface::class);
    $ruleSet = $service->buildRulesFor($blueprint);

    $rules = $ruleSet->getRulesForField('content_json.phone');
    expect($rules)->toHaveCount(2)
        ->and($rules[0])->toBeInstanceOf(NullableRule::class)
        ->and($rules[1])->toBeInstanceOf(PatternRule::class);
});

test('builds rules for cardinality many', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'data_type' => 'string',
        'cardinality' => 'many',
        'validation_rules' => [
            'required' => true,
            'min' => 1,
            'max' => 50,
        ],
    ]);

    $service = app(EntryValidationServiceInterface::class);
    $ruleSet = $service->buildRulesFor($blueprint);

    // Для элементов массива не должно быть required/nullable
    $rules = $ruleSet->getRulesForField('content_json.tags.*');
    expect($rules)->toHaveCount(2)
        ->and($rules[0])->toBeInstanceOf(MinRule::class)
        ->and($rules[1])->toBeInstanceOf(MaxRule::class);
    
    // Проверяем, что нет RequiredRule или NullableRule
    foreach ($rules as $rule) {
        expect($rule)->not->toBeInstanceOf(RequiredRule::class)
            ->and($rule)->not->toBeInstanceOf(NullableRule::class);
    }
});

test('builds rules for nested path', function () {
    $blueprint = Blueprint::factory()->create();
    
    $authorPath = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'json',
    ]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'name',
        'full_path' => 'author.name',
        'parent_id' => $authorPath->id,
        'data_type' => 'string',
        'cardinality' => 'one',
        'validation_rules' => [
            'required' => true,
            'min' => 2,
            'max' => 100,
        ],
    ]);

    $service = app(EntryValidationServiceInterface::class);
    $ruleSet = $service->buildRulesFor($blueprint);

    expect($ruleSet->hasRulesForField('content_json.author.name'))->toBeTrue();
    
    $rules = $ruleSet->getRulesForField('content_json.author.name');
    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($rules[1])->toBeInstanceOf(MinRule::class)
        ->and($rules[2])->toBeInstanceOf(MaxRule::class);
});

test('builds rules for multiple paths', function () {
    $blueprint = Blueprint::factory()->create();
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'phone',
        'full_path' => 'phone',
        'data_type' => 'string',
        'cardinality' => 'one',
        'validation_rules' => ['required' => false],
    ]);

    $service = app(EntryValidationServiceInterface::class);
    $ruleSet = $service->buildRulesFor($blueprint);

    expect($ruleSet->hasRulesForField('content_json.title'))->toBeTrue()
        ->and($ruleSet->hasRulesForField('content_json.phone'))->toBeTrue()
        ->and($ruleSet->getFieldPaths())->toHaveCount(2);
});

test('returns empty rule set for blueprint without paths', function () {
    $blueprint = Blueprint::factory()->create();

    $service = app(EntryValidationServiceInterface::class);
    $ruleSet = $service->buildRulesFor($blueprint);

    expect($ruleSet->isEmpty())->toBeTrue();
});

test('handles path without validation rules', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);

    $service = app(EntryValidationServiceInterface::class);
    $ruleSet = $service->buildRulesFor($blueprint);

    $rules = $ruleSet->getRulesForField('content_json.title');
    expect($rules)->toHaveCount(1)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class);
});

