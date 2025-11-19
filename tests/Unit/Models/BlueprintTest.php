<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Path;
use App\Models\PostType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

/**
 * Unit-тесты для модели Blueprint.
 */

uses(TestCase::class);

test('has no guarded attributes', function () {
    $blueprint = new Blueprint();

    expect($blueprint->getGuarded())->toBe([]);
});

test('casts type to string', function () {
    $blueprint = new Blueprint();
    $casts = $blueprint->getCasts();

    expect($casts)->toHaveKey('type')
        ->and($casts['type'])->toBe('string');
});

test('casts is_default to boolean', function () {
    $blueprint = new Blueprint();
    $casts = $blueprint->getCasts();

    expect($casts)->toHaveKey('is_default')
        ->and($casts['is_default'])->toBe('boolean');
});

test('belongs to post type', function () {
    $blueprint = new Blueprint();
    $relation = $blueprint->postType();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(PostType::class);
});

test('has many own paths', function () {
    $blueprint = new Blueprint();
    $relation = $blueprint->ownPaths();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Path::class);
});

test('has many entries', function () {
    $blueprint = new Blueprint();
    $relation = $blueprint->entries();

    expect($relation)->toBeInstanceOf(HasMany::class);
});

test('has many components relationship', function () {
    $blueprint = new Blueprint();
    $relation = $blueprint->components();

    expect($relation)->toBeInstanceOf(BelongsToMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Blueprint::class);
});

test('uses soft deletes', function () {
    $blueprint = Blueprint::factory()->create();
    
    $blueprint->delete();
    
    expect($blueprint->trashed())->toBeTrue();
    $this->assertSoftDeleted('blueprints', ['id' => $blueprint->id]);
});

test('slug is unique per type and post_type', function () {
    $postType = PostType::factory()->create();
    
    Blueprint::factory()->create([
        'post_type_id' => $postType->id,
        'slug' => 'test-blueprint',
        'type' => 'full',
    ]);
    
    // Можно создать с тем же slug, но другим type
    $component = Blueprint::factory()->create([
        'post_type_id' => null,
        'slug' => 'test-blueprint',
        'type' => 'component',
    ]);
    
    expect($component->slug)->toBe('test-blueprint');
});

test('component blueprint has null post_type_id', function () {
    $blueprint = Blueprint::factory()->create([
        'type' => 'component',
        'post_type_id' => null,
    ]);
    
    expect($blueprint->post_type_id)->toBeNull()
        ->and($blueprint->type)->toBe('component');
});

test('full blueprint has post_type_id', function () {
    $postType = PostType::factory()->create();
    $blueprint = Blueprint::factory()->create([
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);
    
    expect($blueprint->post_type_id)->toBe($postType->id)
        ->and($blueprint->type)->toBe('full');
});

test('can have is_default flag', function () {
    $blueprint = Blueprint::factory()->create([
        'is_default' => true,
    ]);
    
    expect($blueprint->is_default)->toBeTrue();
});

test('invalidates paths cache', function () {
    $blueprint = Blueprint::factory()->create();
    
    // Кэш создается
    $paths = $blueprint->getAllPaths();
    $cacheKey = "blueprint:{$blueprint->id}:all_paths";
    
    expect(cache()->has($cacheKey))->toBeTrue();
    
    // Инвалидация
    $blueprint->invalidatePathsCache();
    
    expect(cache()->has($cacheKey))->toBeFalse();
});

test('get all paths includes own and materialized', function () {
    $blueprint = Blueprint::factory()->create();
    
    // Собственный Path
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'source_component_id' => null,
        'source_path_id' => null,
    ]);
    
    // Материализованный Path
    $component = Blueprint::factory()->create(['type' => 'component']);
    $sourcePath = Path::factory()->create(['blueprint_id' => $component->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'source_component_id' => $component->id,
        'source_path_id' => $sourcePath->id,
    ]);
    
    $allPaths = $blueprint->getAllPaths();
    
    expect($allPaths)->toHaveCount(2);
});

test('get path by full path returns correct path', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'seo.metaTitle',
    ]);
    
    $found = $blueprint->getPathByFullPath('seo.metaTitle');
    
    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($path->id);
});

test('get path by full path returns null for non-existent', function () {
    $blueprint = Blueprint::factory()->create();
    
    $found = $blueprint->getPathByFullPath('non.existent');
    
    expect($found)->toBeNull();
});

