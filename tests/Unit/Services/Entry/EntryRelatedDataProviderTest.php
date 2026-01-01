<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\PostType;
use App\Services\Entry\Providers\EntryRelatedDataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->provider = new EntryRelatedDataProvider();
});

test('getKey returns entryData', function () {
    expect($this->provider->getKey())->toBe('entryData');
});

test('loadData returns empty array for empty ids', function () {
    $result = $this->provider->loadData([]);

    expect($result)->toBe([]);
});

test('loadData loads entry data with postType', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'title' => 'Test Article',
    ]);

    $result = $this->provider->loadData([$entry->id]);

    expect($result)->toHaveKey($entry->id)
        ->and($result[$entry->id])->toBe([
            'entryTitle' => 'Test Article',
            'entryPostType' => 'Article',
        ]);
});

test('loadData excludes deleted entries', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'title' => 'Test Article',
    ]);

    // Удаляем Entry
    $entry->delete();

    $result = $this->provider->loadData([$entry->id]);

    expect($result)->toBe([]);
});

test('loadData handles multiple entries', function () {
    $postType1 = PostType::factory()->create(['name' => 'Article']);
    $postType2 = PostType::factory()->create(['name' => 'Page']);

    $entry1 = Entry::factory()->create([
        'post_type_id' => $postType1->id,
        'title' => 'Article 1',
    ]);

    $entry2 = Entry::factory()->create([
        'post_type_id' => $postType2->id,
        'title' => 'Page 1',
    ]);

    $result = $this->provider->loadData([$entry1->id, $entry2->id]);

    expect($result)->toHaveKeys([$entry1->id, $entry2->id])
        ->and($result[$entry1->id]['entryTitle'])->toBe('Article 1')
        ->and($result[$entry1->id]['entryPostType'])->toBe('Article')
        ->and($result[$entry2->id]['entryTitle'])->toBe('Page 1')
        ->and($result[$entry2->id]['entryPostType'])->toBe('Page');
});

test('loadData handles entry when postType is not loaded', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'title' => 'Test Entry',
    ]);

    // Загружаем Entry без связи postType
    $entryWithoutPostType = Entry::query()
        ->without('postType')
        ->find($entry->id);

    // Симулируем ситуацию, когда postType не загружен
    $entryWithoutPostType->setRelation('postType', null);

    $result = $this->provider->loadData([$entryWithoutPostType->id]);

    // Provider должен обработать это корректно, загрузив postType самостоятельно
    expect($result)->toHaveKey($entry->id)
        ->and($result[$entry->id])->toBe([
            'entryTitle' => 'Test Entry',
            'entryPostType' => 'Article',
        ]);
});

test('formatData formats entry correctly', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'title' => 'Test Article',
    ]);

    $result = $this->provider->formatData($entry);

    expect($result)->toBe([
        'entryTitle' => 'Test Article',
        'entryPostType' => 'Article',
    ]);
});

test('formatData throws exception for invalid type', function () {
    expect(fn() => $this->provider->formatData('invalid'))
        ->toThrow(\InvalidArgumentException::class, 'EntryRelatedDataProvider::formatData() expects Entry instance');
});

