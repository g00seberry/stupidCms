<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface;
use App\Domain\Blueprint\Validation\BlueprintContentValidator;
use App\Domain\Blueprint\Validation\EntryValidationServiceInterface;
use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('builds rules for simple path', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);

    $validator = app(BlueprintContentValidator::class);
    $rules = $validator->buildRules($blueprint);

    expect($rules)->toHaveKey('content_json.title');
    expect($rules['content_json.title'])->toContain('required');
    expect($rules['content_json.title'])->toContain('string');
});

test('builds rules for nested path', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'name',
        'full_path' => 'author.name',
        'data_type' => 'string',
        'is_required' => false,
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);

    $validator = app(BlueprintContentValidator::class);
    $rules = $validator->buildRules($blueprint);

    expect($rules)->toHaveKey('content_json.author.name');
    expect($rules['content_json.author.name'])->toContain('nullable');
    expect($rules['content_json.author.name'])->toContain('string');
});

test('builds rules with validation rules min max', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => [
            'min' => 1,
            'max' => 500,
        ],
    ]);

    $validator = app(BlueprintContentValidator::class);
    $rules = $validator->buildRules($blueprint);

    expect($rules['content_json.title'])->toContain('required');
    expect($rules['content_json.title'])->toContain('string');
    expect($rules['content_json.title'])->toContain('min:1');
    expect($rules['content_json.title'])->toContain('max:500');
});

test('builds rules with pattern validation', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'phone',
        'full_path' => 'phone',
        'data_type' => 'string',
        'is_required' => false,
        'cardinality' => 'one',
        'validation_rules' => [
            'pattern' => '^\\+?[1-9]\\d{1,14}$',
        ],
    ]);

    $validator = app(BlueprintContentValidator::class);
    $rules = $validator->buildRules($blueprint);

    expect($rules['content_json.phone'])->toContain('nullable');
    expect($rules['content_json.phone'])->toContain('string');
    expect($rules['content_json.phone'])->toContain('regex:/^\\+?[1-9]\\d{1,14}$/');
});

test('builds rules for cardinality many', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'many',
        'validation_rules' => [
            'min' => 1,
            'max' => 50,
        ],
    ]);

    $validator = app(BlueprintContentValidator::class);
    $rules = $validator->buildRules($blueprint);

    // Правило для самого массива
    expect($rules)->toHaveKey('content_json.tags');
    expect($rules['content_json.tags'])->toContain('required');
    expect($rules['content_json.tags'])->toContain('array');

    // Правило для элементов массива
    expect($rules)->toHaveKey('content_json.tags.*');
    expect($rules['content_json.tags.*'])->not->toContain('required');
    expect($rules['content_json.tags.*'])->not->toContain('nullable');
    expect($rules['content_json.tags.*'])->toContain('string');
    expect($rules['content_json.tags.*'])->toContain('min:1');
    expect($rules['content_json.tags.*'])->toContain('max:50');
});

test('builds rules for nullable cardinality many', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'data_type' => 'string',
        'is_required' => false,
        'cardinality' => 'many',
        'validation_rules' => null,
    ]);

    $validator = app(BlueprintContentValidator::class);
    $rules = $validator->buildRules($blueprint);

    expect($rules['content_json.tags'])->toContain('nullable');
    expect($rules['content_json.tags'])->toContain('array');
    expect($rules['content_json.tags.*'])->toContain('string');
});

test('handles empty blueprint paths', function () {
    $blueprint = Blueprint::factory()->create();

    $validator = app(BlueprintContentValidator::class);
    $rules = $validator->buildRules($blueprint);

    expect($rules)->toBeEmpty();
});

test('builds rules for multiple paths', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'description',
        'full_path' => 'description',
        'data_type' => 'text',
        'is_required' => false,
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);

    $validator = app(BlueprintContentValidator::class);
    $rules = $validator->buildRules($blueprint);

    expect($rules)->toHaveKey('content_json.title');
    expect($rules)->toHaveKey('content_json.description');
    expect($rules['content_json.title'])->toContain('required');
    expect($rules['content_json.description'])->toContain('nullable');
});

test('builds rules for deep nested paths', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'phone',
        'full_path' => 'author.contacts.phone',
        'data_type' => 'string',
        'is_required' => false,
        'cardinality' => 'one',
        'validation_rules' => [
            'pattern' => '^\\+?[1-9]\\d{1,14}$',
        ],
    ]);

    $validator = app(BlueprintContentValidator::class);
    $rules = $validator->buildRules($blueprint);

    expect($rules)->toHaveKey('content_json.author.contacts.phone');
    expect($rules['content_json.author.contacts.phone'])->toContain('nullable');
    expect($rules['content_json.author.contacts.phone'])->toContain('string');
    expect($rules['content_json.author.contacts.phone'])->toContain('regex:/^\\+?[1-9]\\d{1,14}$/');
});

test('caches validation rules', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);

    $validator = app(BlueprintContentValidator::class);

    // Первый вызов
    $rules1 = $validator->buildRules($blueprint);

    // Второй вызов должен использовать кеш
    $rules2 = $validator->buildRules($blueprint);

    expect($rules1)->toBe($rules2);
    expect($rules1)->toHaveKey('content_json.title');
});

test('handles different data types correctly', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'count',
        'full_path' => 'count',
        'data_type' => 'int',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => ['min' => 0, 'max' => 100],
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'price',
        'full_path' => 'price',
        'data_type' => 'float',
        'is_required' => false,
        'cardinality' => 'one',
        'validation_rules' => ['min' => 0],
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'is_active',
        'full_path' => 'is_active',
        'data_type' => 'bool',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);

    $validator = app(BlueprintContentValidator::class);
    $rules = $validator->buildRules($blueprint);

    expect($rules['content_json.count'])->toContain('integer');
    expect($rules['content_json.price'])->toContain('numeric');
    expect($rules['content_json.is_active'])->toContain('boolean');
});

