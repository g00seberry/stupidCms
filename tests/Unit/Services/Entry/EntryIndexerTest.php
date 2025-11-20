<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\DocRef;
use App\Models\DocValue;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Services\Entry\EntryIndexer;

beforeEach(function () {
    $this->indexer = app(EntryIndexer::class);
});

test('индексация Entry с blueprint создаёт doc_values', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['title' => 'Test Article'],
    ]);

    $this->indexer->index($entry);

    $docValue = DocValue::where('entry_id', $entry->id)->first();

    expect($docValue)->not->toBeNull()
        ->and($docValue->value_string)->toBe('Test Article')
        ->and($docValue->array_index)->toBe(0);
});

test('индексация массива создаёт несколько doc_values', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'data_type' => 'string',
        'cardinality' => 'many',
        'is_indexed' => true,
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['tags' => ['php', 'laravel', 'cms']],
    ]);

    $this->indexer->index($entry);

    $values = DocValue::where('entry_id', $entry->id)->orderBy('array_index')->get();

    expect($values)->toHaveCount(3)
        ->and($values[0]->value_string)->toBe('php')
        ->and($values[0]->array_index)->toBe(1)
        ->and($values[1]->value_string)->toBe('laravel')
        ->and($values[2]->value_string)->toBe('cms');
});

test('индексация ref-поля создаёт doc_refs', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'relatedArticle',
        'full_path' => 'relatedArticle',
        'data_type' => 'ref',
        'is_indexed' => true,
    ]);

    $targetEntry = Entry::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['relatedArticle' => $targetEntry->id],
    ]);

    $this->indexer->index($entry);

    $docRef = DocRef::where('entry_id', $entry->id)->first();

    expect($docRef)->not->toBeNull()
        ->and($docRef->target_entry_id)->toBe($targetEntry->id)
        ->and($docRef->array_index)->toBe(0);
});

test('реиндексация удаляет старые значения', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['title' => 'Old Title'],
    ]);

    // Первая индексация
    $this->indexer->index($entry);
    expect(DocValue::where('entry_id', $entry->id)->count())->toBe(1);

    // Обновление
    $entry->data_json = ['title' => 'New Title'];
    $entry->save();

    // Реиндексация
    $this->indexer->index($entry);

    $values = DocValue::where('entry_id', $entry->id)->get();

    expect($values)->toHaveCount(1)
        ->and($values[0]->value_string)->toBe('New Title');
});

test('Entry без blueprint не индексируется', function () {
    $postType = PostType::factory()->create(['blueprint_id' => null]);
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['title' => 'Legacy Entry'],
    ]);

    $this->indexer->index($entry);

    expect(DocValue::where('entry_id', $entry->id)->count())->toBe(0);
});
