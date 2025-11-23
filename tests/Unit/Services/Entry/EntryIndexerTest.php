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
        'cardinality' => 'one',
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
        ->and($docValue->array_index)->toBeNull()
        ->and($docValue->value_int)->toBeNull()
        ->and($docValue->value_float)->toBeNull()
        ->and($docValue->value_bool)->toBeNull();
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
        'cardinality' => 'one',
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
        ->and($docRef->array_index)->toBeNull()
        ->and(DocValue::where('entry_id', $entry->id)->count())->toBe(0);
});

test('реиндексация удаляет старые значения', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'cardinality' => 'one',
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

test('индексация явно очищает остальные value_* колонки', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'price',
        'full_path' => 'price',
        'data_type' => 'int',
        'cardinality' => 'one',
        'is_indexed' => true,
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['price' => 100],
    ]);

    $this->indexer->index($entry);

    $docValue = DocValue::where('entry_id', $entry->id)->first();

    expect($docValue->value_int)->toBe(100)
        ->and($docValue->value_string)->toBeNull()
        ->and($docValue->value_float)->toBeNull()
        ->and($docValue->value_bool)->toBeNull()
        ->and($docValue->value_datetime)->toBeNull()
        ->and($docValue->value_text)->toBeNull()
        ->and($docValue->value_json)->toBeNull();
});

test('индексация массива устанавливает array_index', function () {
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
        'data_json' => ['tags' => ['php', 'laravel']],
    ]);

    $this->indexer->index($entry);

    $values = DocValue::where('entry_id', $entry->id)->orderBy('array_index')->get();

    expect($values)->toHaveCount(2)
        ->and($values[0]->array_index)->toBe(1)
        ->and($values[1]->array_index)->toBe(2);
});

test('ref-типы не записываются в doc_values', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'relatedArticle',
        'full_path' => 'relatedArticle',
        'data_type' => 'ref',
        'cardinality' => 'one',
        'is_indexed' => true,
    ]);

    $targetEntry = Entry::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['relatedArticle' => $targetEntry->id],
    ]);

    $this->indexer->index($entry);

    expect(DocValue::where('entry_id', $entry->id)->count())->toBe(0)
        ->and(DocRef::where('entry_id', $entry->id)->count())->toBe(1);
});

test('индексация date-типа сохраняется в value_datetime с временем 00:00:00', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'published_date',
        'full_path' => 'published_date',
        'data_type' => 'date',
        'cardinality' => 'one',
        'is_indexed' => true,
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['published_date' => '2024-01-15'],
    ]);

    $this->indexer->index($entry);

    $docValue = DocValue::where('entry_id', $entry->id)->first();

    expect($docValue)->not->toBeNull()
        ->and($docValue->value_datetime)->not->toBeNull()
        ->and($docValue->value_datetime->format('Y-m-d'))->toBe('2024-01-15')
        ->and($docValue->value_datetime->format('H:i:s'))->toBe('00:00:00');
});

test('индексация text-типа сохраняется в value_text', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'body',
        'full_path' => 'body',
        'data_type' => 'text',
        'cardinality' => 'one',
        'is_indexed' => true,
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['body' => 'Long text content here'],
    ]);

    $this->indexer->index($entry);

    $docValue = DocValue::where('entry_id', $entry->id)->first();

    expect($docValue)->not->toBeNull()
        ->and($docValue->value_text)->toBe('Long text content here')
        ->and($docValue->value_string)->toBeNull();
});
