<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\DocRef;
use App\Models\DocValue;
use App\Models\Entry;
use App\Models\Path;

/**
 * Unit-тесты для трейта HasDocumentData
 */

test('sync document index creates doc values for indexed paths', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);
    
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => ['title' => 'Test Title'],
    ]);
    
    expect($entry->values()->count())->toBeGreaterThan(0);
    
    $value = $entry->values()->where('path_id', $path->id)->first();
    expect($value->value_string)->toBe('Test Title');
});

test('sync document index skips non-indexed paths', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'notes',
        'data_type' => 'text',
        'is_indexed' => false,
    ]);
    
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => ['notes' => 'Internal notes'],
    ]);
    
    $value = $entry->values()->where('path_id', $path->id)->first();
    expect($value)->toBeNull();
});

test('sync document index handles nested paths', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'seo.metaTitle',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);
    
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => [
            'seo' => [
                'metaTitle' => 'SEO Title',
            ],
        ],
    ]);
    
    $value = $entry->values()->where('path_id', $path->id)->first();
    expect($value)->not->toBeNull()
        ->and($value->value_string)->toBe('SEO Title');
});

test('sync document index handles cardinality many', function () {
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
        'data_json' => [
            'tags' => ['tag1', 'tag2', 'tag3'],
        ],
    ]);
    
    $values = $entry->values()->where('path_id', $path->id)->orderBy('idx')->get();
    
    expect($values)->toHaveCount(3)
        ->and($values[0]->value_string)->toBe('tag1')
        ->and($values[1]->value_string)->toBe('tag2')
        ->and($values[2]->value_string)->toBe('tag3');
});

test('sync document index handles ref type', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'relatedArticle',
        'data_type' => 'ref',
        'cardinality' => 'one',
        'is_indexed' => true,
    ]);
    
    $targetEntry = Entry::factory()->create();
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => [
            'relatedArticle' => $targetEntry->id,
        ],
    ]);
    
    $ref = DocRef::where('entry_id', $entry->id)
        ->where('path_id', $path->id)
        ->first();
    
    expect($ref)->not->toBeNull()
        ->and($ref->target_entry_id)->toBe($targetEntry->id);
});

test('sync document index handles ref type with many cardinality', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'relatedArticles',
        'data_type' => 'ref',
        'cardinality' => 'many',
        'is_indexed' => true,
    ]);
    
    $target1 = Entry::factory()->create();
    $target2 = Entry::factory()->create();
    
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => [
            'relatedArticles' => [$target1->id, $target2->id],
        ],
    ]);
    
    $refs = DocRef::where('entry_id', $entry->id)
        ->where('path_id', $path->id)
        ->orderBy('idx')
        ->get();
    
    expect($refs)->toHaveCount(2)
        ->and($refs[0]->target_entry_id)->toBe($target1->id)
        ->and($refs[1]->target_entry_id)->toBe($target2->id);
});

test('where path scope filters by path value', function () {
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

test('where ref scope finds entries referencing target', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'full_path' => 'author',
        'data_type' => 'ref',
        'is_indexed' => true,
    ]);
    
    $author = Entry::factory()->create();
    
    $entry1 = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => ['author' => $author->id],
    ]);
    
    $entry2 = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => ['author' => 999],
    ]);
    
    $results = Entry::whereRef('author', $author->id)->get();
    
    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($entry1->id);
});

test('get value field for type returns correct field', function () {
    $entry = new Entry();
    
    // Используем рефлексию для тестирования protected метода
    $reflection = new ReflectionClass($entry);
    $method = $reflection->getMethod('getValueFieldForType');
    $method->setAccessible(true);
    
    expect($method->invoke($entry, 'string'))->toBe('value_string')
        ->and($method->invoke($entry, 'int'))->toBe('value_int')
        ->and($method->invoke($entry, 'float'))->toBe('value_float')
        ->and($method->invoke($entry, 'bool'))->toBe('value_bool')
        ->and($method->invoke($entry, 'text'))->toBe('value_text')
        ->and($method->invoke($entry, 'json'))->toBe('value_json');
});

test('sync document index clears old values before creating new', function () {
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
    
    $oldValueCount = DocValue::where('entry_id', $entry->id)->count();
    
    // Обновляем
    $entry->data_json = ['title' => 'New Title'];
    $entry->save();
    
    $newValueCount = DocValue::where('entry_id', $entry->id)->count();
    
    // Количество записей не должно увеличиться
    expect($newValueCount)->toBe($oldValueCount);
    
    $value = DocValue::where('entry_id', $entry->id)
        ->where('path_id', $path->id)
        ->first();
    
    expect($value->value_string)->toBe('New Title');
});

test('handles different data types correctly', function () {
    $blueprint = Blueprint::factory()->create();
    
    $paths = [
        'stringField' => ['data_type' => 'string', 'value' => 'test string'],
        'intField' => ['data_type' => 'int', 'value' => 42],
        'floatField' => ['data_type' => 'float', 'value' => 3.14],
        'boolField' => ['data_type' => 'bool', 'value' => true],
        'textField' => ['data_type' => 'text', 'value' => 'long text...'],
        'jsonField' => ['data_type' => 'json', 'value' => ['key' => 'value']],
    ];
    
    $pathModels = [];
    foreach ($paths as $name => $config) {
        $pathModels[$name] = Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => $name,
            'data_type' => $config['data_type'],
            'is_indexed' => true,
        ]);
    }
    
    $dataJson = [];
    foreach ($paths as $name => $config) {
        $dataJson[$name] = $config['value'];
    }
    
    $entry = Entry::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_json' => $dataJson,
    ]);
    
    // Проверяем каждый тип
    foreach ($paths as $name => $config) {
        $value = DocValue::where('entry_id', $entry->id)
            ->where('path_id', $pathModels[$name]->id)
            ->first();
        
        expect($value)->not->toBeNull();
        
        $actualValue = $value->getValue();
        expect($actualValue)->toBe($config['value']);
    }
});

