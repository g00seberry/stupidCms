<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\DocValue;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;

/**
 * Интеграционные тесты для Blueprint системы
 * 
 * Проверяет полный цикл: Blueprint → Path → Entry → Индексация
 */

test('entry is indexed on create with blueprint', function () {
    $postType = PostType::factory()->create();
    $blueprint = Blueprint::factory()->create([
        'post_type_id' => $postType->id,
        'type' => 'full',
    ]);
    
    // Создаем indexed Path
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'content',
        'data_type' => 'text',
        'is_indexed' => true,
    ]);
    
    // Создаем Entry с данными
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'blueprint_id' => $blueprint->id,
        'data_json' => [
            'content' => 'Test content for indexing',
        ],
    ]);
    
    // Проверяем, что создан DocValue
    $this->assertDatabaseHas('doc_values', [
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'value_text' => 'Test content for indexing',
    ]);
});

test('entry is re-indexed on update', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);
    
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => ['title' => 'Old Title'],
    ]);
    
    $oldValue = DocValue::where('entry_id', $entry->id)->first();
    expect($oldValue->value_string)->toBe('Old Title');
    
    // Обновляем Entry
    $entry->update([
        'data_json' => ['title' => 'New Title'],
    ]);
    
    $newValue = DocValue::where('entry_id', $entry->id)->first();
    expect($newValue->value_string)->toBe('New Title');
});

test('composite blueprint indexes all paths', function () {
    $blueprint = Blueprint::factory()->create(['type' => 'full']);
    $component = Blueprint::factory()->create(['type' => 'component']);
    
    // Собственный Path
    $ownPath = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'content',
        'data_type' => 'text',
        'is_indexed' => true,
    ]);
    
    // Path в компоненте
    $componentPath = Path::factory()->create([
        'blueprint_id' => $component->id,
        'full_path' => 'metaTitle',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);
    
    // Attach компонента (материализация)
    $blueprint->components()->attach($component->id, ['path_prefix' => 'seo']);
    $blueprint->materializeComponentPaths($component, 'seo');
    
    $materializedPath = Path::where([
        'blueprint_id' => $blueprint->id,
        'source_component_id' => $component->id,
    ])->first();
    
    // Создаем Entry
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => [
            'content' => 'Article content',
            'seo' => [
                'metaTitle' => 'SEO Title',
            ],
        ],
    ]);
    
    // Проверяем индексы для обоих путей
    $this->assertDatabaseHas('doc_values', [
        'entry_id' => $entry->id,
        'path_id' => $ownPath->id,
        'value_text' => 'Article content',
    ]);
    
    $this->assertDatabaseHas('doc_values', [
        'entry_id' => $entry->id,
        'path_id' => $materializedPath->id,
        'value_string' => 'SEO Title',
    ]);
});

test('where path scope finds entries by indexed value', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'category',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);
    
    $entry1 = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => ['category' => 'Tech'],
    ]);
    
    $entry2 = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => ['category' => 'News'],
    ]);
    
    $results = Entry::wherePath('category', '=', 'Tech')->get();
    
    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($entry1->id);
});

test('cardinality many creates multiple doc values', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'tags',
        'data_type' => 'string',
        'cardinality' => 'many',
        'is_indexed' => true,
    ]);
    
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => ['tags' => ['php', 'laravel', 'testing']],
    ]);
    
    $values = DocValue::where('entry_id', $entry->id)
        ->where('path_id', $path->id)
        ->orderBy('idx')
        ->get();
    
    expect($values)->toHaveCount(3)
        ->and($values[0]->value_string)->toBe('php')
        ->and($values[1]->value_string)->toBe('laravel')
        ->and($values[2]->value_string)->toBe('testing')
        ->and($values[0]->idx)->toBe(0)
        ->and($values[1]->idx)->toBe(1)
        ->and($values[2]->idx)->toBe(2);
});

test('non-indexed paths do not create doc values', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'notes',
        'data_type' => 'text',
        'is_indexed' => false, // НЕ индексируется
    ]);
    
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => ['notes' => 'Internal notes...'],
    ]);
    
    $this->assertDatabaseMissing('doc_values', [
        'entry_id' => $entry->id,
        'path_id' => $path->id,
    ]);
});

test('updating path is_indexed triggers reindexing', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'excerpt',
        'data_type' => 'text',
        'is_indexed' => false,
    ]);
    
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => ['excerpt' => 'Short excerpt'],
    ]);
    
    // Индекса нет
    $this->assertDatabaseMissing('doc_values', [
        'entry_id' => $entry->id,
        'path_id' => $path->id,
    ]);
    
    // Включаем индексацию
    $path->update(['is_indexed' => true]);
    
    // Обновляем cache Blueprint
    $blueprint->invalidatePathsCache();
    
    // Триггерим реиндексацию вручную
    $entry->refresh();
    $entry->syncDocumentIndex();
    
    // Индекс должен появиться
    $this->assertDatabaseHas('doc_values', [
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'value_text' => 'Short excerpt',
    ]);
});

test('deleting entry cascades to doc values', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'field',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);
    
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => ['field' => 'value'],
    ]);
    
    // Проверяем, что запись создана
    $docValue = $entry->values()->where('path_id', $path->id)->first();
    expect($docValue)->not->toBeNull();
    
    $entryId = $entry->id;
    
    $entry->forceDelete();
    
    // Проверяем, что doc_values удалены
    $this->assertDatabaseMissing('doc_values', ['entry_id' => $entryId]);
});

test('blueprint caching works correctly', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->count(3)->create(['blueprint_id' => $blueprint->id]);
    
    // Первый вызов - кэширует
    $paths1 = $blueprint->getAllPaths();
    $cacheKey = "blueprint:{$blueprint->id}:all_paths";
    
    expect(cache()->has($cacheKey))->toBeTrue();
    
    // Второй вызов - из кэша
    $paths2 = $blueprint->getAllPaths();
    
    expect($paths1->count())->toBe($paths2->count());
    
    // Инвалидация
    $blueprint->invalidatePathsCache();
    
    expect(cache()->has($cacheKey))->toBeFalse();
});

