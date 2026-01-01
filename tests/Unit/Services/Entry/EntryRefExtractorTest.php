<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Services\Entry\EntryRefExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->extractor = new EntryRefExtractor();
});

test('extractRefEntryIds возвращает пустой массив если нет Blueprint', function () {
    $postType = PostType::factory()->create(['blueprint_id' => null]);
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['author' => 42],
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toBe([]);
});

test('extractRefEntryIds возвращает пустой массив если нет ref-полей', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['title' => 'Test'],
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toBe([]);
});

test('extractRefEntryIds извлекает ID из одиночного ref-поля', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['author' => 42],
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toBe([42]);
});

test('extractRefEntryIds извлекает ID из массива ref-полей', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'relatedArticles',
        'full_path' => 'relatedArticles',
        'data_type' => 'ref',
        'cardinality' => 'many',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['relatedArticles' => [42, 43, 44]],
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toContain(42, 43, 44)
        ->and(count($result))->toBe(3);
});

test('extractRefEntryIds извлекает ID из вложенных ref-полей', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    $parentPath = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'json',
    ]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $parentPath->id,
        'name' => 'profile',
        'full_path' => 'author.profile',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => [
            'author' => [
                'profile' => 42,
            ],
        ],
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toBe([42]);
});

test('extractRefEntryIds обрабатывает несколько ref-полей', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'relatedArticles',
        'full_path' => 'relatedArticles',
        'data_type' => 'ref',
        'cardinality' => 'many',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => [
            'author' => 42,
            'relatedArticles' => [43, 44],
        ],
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toContain(42, 43, 44)
        ->and(count($result))->toBe(3);
});

test('extractRefEntryIds возвращает уникальные ID', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'coAuthor',
        'full_path' => 'coAuthor',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => [
            'author' => 42,
            'coAuthor' => 42, // Дубликат
        ],
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toBe([42])
        ->and(count($result))->toBe(1);
});

test('extractRefEntryIds игнорирует null значения', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['author' => null],
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toBe([]);
});

test('extractRefEntryIds игнорирует несуществующие поля', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['title' => 'Test'], // Нет поля author
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toBe([]);
});

test('extractRefEntryIds обрабатывает числовые строки', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['author' => '42'], // Строка вместо числа
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toBe([42]);
});

test('extractRefEntryIds фильтрует нечисловые значения из массивов', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'relatedArticles',
        'full_path' => 'relatedArticles',
        'data_type' => 'ref',
        'cardinality' => 'many',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => [
            'relatedArticles' => [42, '43', 'invalid', 44, null],
        ],
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toContain(42, 43, 44)
        ->and(count($result))->toBe(3);
});

test('extractRefEntryIds обрабатывает пустой data_json', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => [],
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toBe([]);
});

test('extractRefEntryIds обрабатывает null data_json', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => null,
    ]);

    $result = $this->extractor->extractRefEntryIds($entry);

    expect($result)->toBe([]);
});

