<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use App\Rules\UniqueEntrySlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->postType = PostType::factory()->create(['name' => 'Article']);
});

test('passes for unique slug', function () {
    $rule = new UniqueEntrySlug();
    
    $validator = Validator::make(
        ['slug' => 'unique-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails for duplicate slug globally', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'duplicate-slug',
    ]);

    $rule = new UniqueEntrySlug();
    
    $validator = Validator::make(
        ['slug' => 'duplicate-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('fails for duplicate slug in different post type', function () {
    $otherType = PostType::factory()->create(['name' => 'Page']);
    
    Entry::factory()->create([
        'post_type_id' => $otherType->id,
        'author_id' => $this->user->id,
        'slug' => 'same-slug',
    ]);

    $rule = new UniqueEntrySlug();
    
    $validator = Validator::make(
        ['slug' => 'same-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('passes for same entry on update', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'existing-slug',
    ]);

    $rule = new UniqueEntrySlug($entry->id);
    
    $validator = Validator::make(
        ['slug' => 'existing-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails for duplicate slug even if soft-deleted', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'deleted-slug',
    ]);
    
    $entry->delete(); // Soft delete

    $rule = new UniqueEntrySlug();
    
    $validator = Validator::make(
        ['slug' => 'deleted-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('passes for empty slug', function () {
    $rule = new UniqueEntrySlug();
    
    $validator = Validator::make(
        ['slug' => ''],
        ['slug' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

