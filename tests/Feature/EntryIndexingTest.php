<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\DocValue;
use App\Models\DocRef;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('создание Entry автоматически индексирует данные', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'article']);
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test Entry',
        'data_json' => [
            'title' => 'My Article',
        ],
    ]);

    // Проверить индексацию
    $docValue = DocValue::where('entry_id', $entry->id)->first();

    expect($docValue)->not->toBeNull()
        ->and($docValue->value_string)->toBe('My Article');
});

test('обновление Entry реиндексирует данные', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test',
        'data_json' => ['title' => 'Old Title'],
    ]);

    // Обновить
    $entry->update(['data_json' => ['title' => 'New Title']]);

    $docValue = DocValue::where('entry_id', $entry->id)->first();

    expect($docValue->value_string)->toBe('New Title');
});

test('удаление Entry очищает индексы', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'is_indexed' => true,
    ]);

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test',
        'data_json' => ['title' => 'Title'],
    ]);

    $entryId = $entry->id;

    $entry->delete();

    $docValuesCount = DocValue::where('entry_id', $entryId)->count();

    expect($docValuesCount)->toBe(0);
});

test('индексация массивов с array_index', function () {
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

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test',
        'data_json' => ['tags' => ['cms', 'laravel', 'php']],
    ]);

    $docValues = DocValue::where('entry_id', $entry->id)->orderBy('array_index')->get();

    expect($docValues)->toHaveCount(3)
        ->and($docValues->pluck('value_string')->all())->toBe(['cms', 'laravel', 'php'])
        ->and($docValues->pluck('array_index')->all())->toBe([1, 2, 3]);
});

test('индексация ref полей', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'related_article',
        'full_path' => 'related_article',
        'data_type' => 'ref',
        'is_indexed' => true,
    ]);

    $relatedEntry = Entry::factory()->create();

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test',
        'data_json' => ['related_article' => $relatedEntry->id],
    ]);

    $docRef = DocRef::where('entry_id', $entry->id)->first();

    expect($docRef)->not->toBeNull()
        ->and($docRef->target_entry_id)->toBe($relatedEntry->id);
});

test('wherePath фильтрует Entry по индексированным полям', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Entry 1',
        'data_json' => ['author' => 'John Doe'],
    ]);

    Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Entry 2',
        'data_json' => ['author' => 'Jane Smith'],
    ]);

    $entries = Entry::wherePath('author', '=', 'John Doe')->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->title)->toBe('Entry 1');
});

test('whereRef фильтрует Entry по ref полям', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'related',
        'full_path' => 'related',
        'data_type' => 'ref',
        'is_indexed' => true,
    ]);

    $targetEntry = Entry::factory()->create();

    Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Entry 1',
        'data_json' => ['related' => $targetEntry->id],
    ]);

    Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Entry 2',
        'data_json' => ['related' => 999],
    ]);

    $entries = Entry::whereRef('related', $targetEntry->id)->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->title)->toBe('Entry 1');
});

