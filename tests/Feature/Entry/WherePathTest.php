<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Services\Entry\EntryIndexer;

beforeEach(function () {
    $this->blueprint = Blueprint::factory()->create();
    $this->postType = PostType::factory()->create(['blueprint_id' => $this->blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'name' => 'price',
        'full_path' => 'price',
        'data_type' => 'int',
        'is_indexed' => true,
    ]);

    $this->indexer = app(EntryIndexer::class);
});

test('wherePath находит Entry по строке', function () {
    $entry1 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'Laravel Tutorial'],
    ]);

    $entry2 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'PHP Basics'],
    ]);

    $this->indexer->index($entry1);
    $this->indexer->index($entry2);

    $results = Entry::wherePath('title', '=', 'Laravel Tutorial')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($entry1->id);
});

test('wherePath работает с операторами сравнения', function () {
    $entry1 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['price' => 50],
    ]);

    $entry2 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['price' => 150],
    ]);

    $this->indexer->index($entry1);
    $this->indexer->index($entry2);

    $results = Entry::wherePath('price', '>', 100)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($entry2->id);
});

test('wherePathIn находит Entry по списку значений', function () {
    $entry1 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'Article 1'],
    ]);

    $entry2 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'Article 2'],
    ]);

    $entry3 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'Article 3'],
    ]);

    $this->indexer->index($entry1);
    $this->indexer->index($entry2);
    $this->indexer->index($entry3);

    $results = Entry::wherePathIn('title', ['Article 1', 'Article 3'])->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id')->all())->toContain($entry1->id, $entry3->id);
});

test('wherePathExists фильтрует Entry с заполненным полем', function () {
    $entry1 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'With Title'],
    ]);

    $entry2 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => [],
    ]);

    $this->indexer->index($entry1);
    $this->indexer->index($entry2);

    $results = Entry::wherePathExists('title')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($entry1->id);
});
