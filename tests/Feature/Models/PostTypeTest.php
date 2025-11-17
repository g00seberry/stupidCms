<?php

declare(strict_types=1);

use App\Models\PostType;
use App\Models\Entry;
use App\Domain\PostTypes\PostTypeOptions;

/**
 * Feature-тесты для модели PostType.
 *
 * Проверяют реальное взаимодействие модели с базой данных,
 * создание, связи и работу с PostTypeOptions.
 */

test('post type can be created', function () {
    $postType = PostType::factory()->create([
        'slug' => 'article',
        'name' => 'Article',
    ]);

    expect($postType)->toBeInstanceOf(PostType::class)
        ->and($postType->slug)->toBe('article')
        ->and($postType->name)->toBe('Article')
        ->and($postType->exists)->toBeTrue();

    $this->assertDatabaseHas('post_types', [
        'id' => $postType->id,
        'slug' => 'article',
    ]);
});

test('post type has unique slug', function () {
    PostType::factory()->create(['slug' => 'unique-type']);

    $this->expectException(\Illuminate\Database\QueryException::class);

    PostType::factory()->create(['slug' => 'unique-type']);
});

test('post type can have multiple entries', function () {
    $postType = PostType::factory()->create();

    $entry1 = Entry::factory()->create(['post_type_id' => $postType->id]);
    $entry2 = Entry::factory()->create(['post_type_id' => $postType->id]);

    $postType->load('entries');

    expect($postType->entries)->toHaveCount(2)
        ->and($postType->entries->pluck('id')->toArray())->toContain($entry1->id, $entry2->id);
});

test('post type options are stored correctly', function () {
    $options = [
        'taxonomies' => [1, 2, 3],
        'custom_field' => 'value',
    ];

    $postType = PostType::factory()->create([
        'options_json' => $options,
    ]);

    $postType->refresh();

    expect($postType->options_json)->toBeInstanceOf(PostTypeOptions::class)
        ->and($postType->options_json->taxonomies)->toBe([1, 2, 3])
        ->and($postType->options_json->getField('custom_field'))->toBe('value');
});

test('post type options can be empty', function () {
    $postType = PostType::factory()->create([
        'options_json' => [],
    ]);

    $postType->refresh();

    expect($postType->options_json)->toBeInstanceOf(PostTypeOptions::class)
        ->and($postType->options_json->taxonomies)->toBe([])
        ->and($postType->options_json->fields)->toBe([]);
});

test('post type options cast works on retrieval', function () {
    $postType = PostType::factory()->withOptions([
        'taxonomies' => [1, 5, 10],
        'icon' => 'fa-file',
    ])->create();

    $postType->refresh();

    expect($postType->options_json)->toBeInstanceOf(PostTypeOptions::class)
        ->and($postType->options_json->getAllowedTaxonomies())->toBe([1, 5, 10])
        ->and($postType->options_json->getField('icon'))->toBe('fa-file');
});

test('post type options taxonomy check works', function () {
    $postType = PostType::factory()->withOptions([
        'taxonomies' => [1, 2, 3],
    ])->create();

    $postType->refresh();

    expect($postType->options_json->isTaxonomyAllowed(1))->toBeTrue()
        ->and($postType->options_json->isTaxonomyAllowed(2))->toBeTrue()
        ->and($postType->options_json->isTaxonomyAllowed(5))->toBeFalse();
});

test('post type options allows all taxonomies when list is empty', function () {
    $postType = PostType::factory()->create([
        'options_json' => [],
    ]);

    $postType->refresh();

    expect($postType->options_json->isTaxonomyAllowed(1))->toBeTrue()
        ->and($postType->options_json->isTaxonomyAllowed(999))->toBeTrue();
});

test('post type options has field check works', function () {
    $postType = PostType::factory()->withOptions([
        'icon' => 'fa-file',
        'show_in_menu' => true,
    ])->create();

    $postType->refresh();

    expect($postType->options_json->hasField('icon'))->toBeTrue()
        ->and($postType->options_json->hasField('show_in_menu'))->toBeTrue()
        ->and($postType->options_json->hasField('non_existent'))->toBeFalse();
});

test('post type options get field with default works', function () {
    $postType = PostType::factory()->withOptions([
        'icon' => 'fa-file',
    ])->create();

    $postType->refresh();

    expect($postType->options_json->getField('icon'))->toBe('fa-file')
        ->and($postType->options_json->getField('non_existent'))->toBeNull()
        ->and($postType->options_json->getField('non_existent', 'default'))->toBe('default');
});

test('post type can be updated', function () {
    $postType = PostType::factory()->create([
        'name' => 'Old Name',
    ]);

    $postType->update(['name' => 'New Name']);

    expect($postType->name)->toBe('New Name');

    $this->assertDatabaseHas('post_types', [
        'id' => $postType->id,
        'name' => 'New Name',
    ]);
});

test('post type options can be updated', function () {
    $postType = PostType::factory()->create([
        'options_json' => ['taxonomies' => [1, 2]],
    ]);

    $postType->update([
        'options_json' => ['taxonomies' => [3, 4, 5]],
    ]);

    $postType->refresh();

    expect($postType->options_json->getAllowedTaxonomies())->toBe([3, 4, 5]);
});

test('post type slug cannot be changed to existing slug', function () {
    PostType::factory()->create(['slug' => 'existing']);
    $postType = PostType::factory()->create(['slug' => 'unique']);

    $this->expectException(\Illuminate\Database\QueryException::class);

    $postType->update(['slug' => 'existing']);
});

