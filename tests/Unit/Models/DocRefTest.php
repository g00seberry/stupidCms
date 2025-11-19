<?php

declare(strict_types=1);

use App\Models\DocRef;
use App\Models\Entry;
use App\Models\Path;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

/**
 * Unit-тесты для модели DocRef.
 */

uses(TestCase::class);

test('has no guarded attributes', function () {
    $docRef = new DocRef();

    expect($docRef->getGuarded())->toBe([]);
});

test('does not use timestamps', function () {
    $docRef = new DocRef();

    expect($docRef->timestamps)->toBeFalse();
});

test('belongs to entry', function () {
    $docRef = new DocRef();
    $relation = $docRef->entry();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Entry::class);
});

test('belongs to path', function () {
    $docRef = new DocRef();
    $relation = $docRef->path();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Path::class);
});

test('belongs to target entry', function () {
    $docRef = new DocRef();
    $relation = $docRef->targetEntry();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Entry::class);
});

test('idx defaults to 0', function () {
    $entry = Entry::factory()->create();
    $targetEntry = Entry::factory()->create();
    $path = Path::factory()->create(['data_type' => 'ref']);
    
    $docRef = DocRef::create([
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'target_entry_id' => $targetEntry->id,
    ]);

    expect($docRef->idx)->toBe(0);
});

test('idx can be greater than 0 for many cardinality', function () {
    $entry = Entry::factory()->create();
    $targetEntry = Entry::factory()->create();
    $path = Path::factory()->create([
        'data_type' => 'ref',
        'cardinality' => 'many',
    ]);
    
    $docRef = DocRef::create([
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'idx' => 2,
        'target_entry_id' => $targetEntry->id,
    ]);

    expect($docRef->idx)->toBe(2);
});

test('composite primary key entry_id path_id idx', function () {
    $entry = Entry::factory()->create();
    $target1 = Entry::factory()->create();
    $target2 = Entry::factory()->create();
    $path = Path::factory()->create(['data_type' => 'ref']);
    
    $docRef1 = DocRef::create([
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'idx' => 0,
        'target_entry_id' => $target1->id,
    ]);
    
    $docRef2 = DocRef::create([
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'idx' => 1,
        'target_entry_id' => $target2->id,
    ]);
    
    expect($docRef1->target_entry_id)->toBe($target1->id)
        ->and($docRef2->target_entry_id)->toBe($target2->id);
});

test('cascade deletes when entry is deleted', function () {
    $entry = Entry::factory()->create();
    $targetEntry = Entry::factory()->create();
    $path = Path::factory()->create(['data_type' => 'ref']);
    
    DocRef::create([
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'target_entry_id' => $targetEntry->id,
    ]);
    
    $entry->forceDelete();
    
    $this->assertDatabaseMissing('doc_refs', [
        'entry_id' => $entry->id,
    ]);
});

test('cascade deletes when path is deleted', function () {
    $entry = Entry::factory()->create();
    $targetEntry = Entry::factory()->create();
    $path = Path::factory()->create(['data_type' => 'ref']);
    
    DocRef::create([
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'target_entry_id' => $targetEntry->id,
    ]);
    
    $path->forceDelete();
    
    $this->assertDatabaseMissing('doc_refs', [
        'path_id' => $path->id,
    ]);
});

