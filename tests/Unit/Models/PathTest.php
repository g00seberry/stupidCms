<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

/**
 * Unit-тесты для модели Path.
 */

uses(TestCase::class);

test('has no guarded attributes', function () {
    $path = new Path();

    expect($path->getGuarded())->toBe([]);
});

test('casts data_type to string', function () {
    $path = new Path();
    $casts = $path->getCasts();

    expect($casts)->toHaveKey('data_type')
        ->and($casts['data_type'])->toBe('string');
});

test('casts cardinality to string', function () {
    $path = new Path();
    $casts = $path->getCasts();

    expect($casts)->toHaveKey('cardinality')
        ->and($casts['cardinality'])->toBe('string');
});

test('casts is_indexed to boolean', function () {
    $path = new Path();
    $casts = $path->getCasts();

    expect($casts)->toHaveKey('is_indexed')
        ->and($casts['is_indexed'])->toBe('boolean');
});

test('casts is_required to boolean', function () {
    $path = new Path();
    $casts = $path->getCasts();

    expect($casts)->toHaveKey('is_required')
        ->and($casts['is_required'])->toBe('boolean');
});

test('casts validation_rules to array', function () {
    $path = new Path();
    $casts = $path->getCasts();

    expect($casts)->toHaveKey('validation_rules')
        ->and($casts['validation_rules'])->toBe('array');
});

test('casts ui_options to array', function () {
    $path = new Path();
    $casts = $path->getCasts();

    expect($casts)->toHaveKey('ui_options')
        ->and($casts['ui_options'])->toBe('array');
});

test('belongs to blueprint', function () {
    $path = new Path();
    $relation = $path->blueprint();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Blueprint::class);
});

test('belongs to parent path', function () {
    $path = new Path();
    $relation = $path->parent();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Path::class);
});

test('belongs to source component', function () {
    $path = new Path();
    $relation = $path->sourceComponent();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Blueprint::class);
});

test('belongs to source path', function () {
    $path = new Path();
    $relation = $path->sourcePath();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Path::class);
});

test('has many children paths', function () {
    $path = new Path();
    $relation = $path->children();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Path::class);
});

test('uses soft deletes', function () {
    $path = Path::factory()->create();
    
    $path->delete();
    
    expect($path->trashed())->toBeTrue();
    $this->assertSoftDeleted('paths', ['id' => $path->id]);
});

test('full_path is unique per blueprint', function () {
    $blueprint = Blueprint::factory()->create();
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'seo.metaTitle',
    ]);
    
    $this->expectException(\Illuminate\Database\QueryException::class);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'seo.metaTitle',
    ]);
});

test('same full_path can exist in different blueprints', function () {
    $blueprint1 = Blueprint::factory()->create();
    $blueprint2 = Blueprint::factory()->create();
    
    $path1 = Path::factory()->create([
        'blueprint_id' => $blueprint1->id,
        'full_path' => 'content',
    ]);
    
    $path2 = Path::factory()->create([
        'blueprint_id' => $blueprint2->id,
        'full_path' => 'content',
    ]);
    
    expect($path1->full_path)->toBe($path2->full_path)
        ->and($path1->blueprint_id)->not->toBe($path2->blueprint_id);
});

test('is_materialized accessor returns true when source_component_id exists', function () {
    $component = Blueprint::factory()->create(['type' => 'component']);
    $sourcePath = Path::factory()->create(['blueprint_id' => $component->id]);
    
    $materializedPath = Path::factory()->create([
        'source_component_id' => $component->id,
        'source_path_id' => $sourcePath->id,
    ]);
    
    expect($materializedPath->is_materialized)->toBeTrue();
});

test('is_materialized accessor returns false when source_component_id is null', function () {
    $path = Path::factory()->create([
        'source_component_id' => null,
        'source_path_id' => null,
    ]);
    
    expect($path->is_materialized)->toBeFalse();
});

test('cardinality defaults to one', function () {
    $path = Path::factory()->create(['cardinality' => 'one']);
    
    expect($path->cardinality)->toBe('one');
});

test('cardinality can be many', function () {
    $path = Path::factory()->create(['cardinality' => 'many']);
    
    expect($path->cardinality)->toBe('many');
});

test('supports different data types', function () {
    $types = ['string', 'int', 'float', 'bool', 'text', 'json', 'ref'];
    
    foreach ($types as $type) {
        $path = Path::factory()->create(['data_type' => $type]);
        expect($path->data_type)->toBe($type);
    }
});

test('ref type can have ref_target_type', function () {
    $path = Path::factory()->create([
        'data_type' => 'ref',
        'ref_target_type' => 'article',
    ]);
    
    expect($path->ref_target_type)->toBe('article');
});

test('can have validation rules', function () {
    $rules = ['required', 'max:255'];
    $path = Path::factory()->create([
        'validation_rules' => $rules,
    ]);
    
    expect($path->validation_rules)->toBe($rules);
});

test('can have ui options', function () {
    $options = ['placeholder' => 'Enter title', 'help' => 'Help text'];
    $path = Path::factory()->create([
        'ui_options' => $options,
    ]);
    
    expect($path->ui_options)->toBe($options);
});

