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
    $this->postType = PostType::factory()->create(['slug' => 'article']);
});

test('passes for unique slug', function () {
    $rule = new UniqueEntrySlug('article');
    
    $validator = Validator::make(
        ['slug' => 'unique-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails for duplicate slug in same post type', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'duplicate-slug',
    ]);

    $rule = new UniqueEntrySlug('article');
    
    $validator = Validator::make(
        ['slug' => 'duplicate-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('passes for duplicate slug in different post type', function () {
    $otherType = PostType::factory()->create(['slug' => 'page']);
    
    Entry::factory()->create([
        'post_type_id' => $otherType->id,
        'author_id' => $this->user->id,
        'slug' => 'same-slug',
    ]);

    $rule = new UniqueEntrySlug('article');
    
    $validator = Validator::make(
        ['slug' => 'same-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes for same entry on update', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'existing-slug',
    ]);

    $rule = new UniqueEntrySlug('article', $entry->id);
    
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

    $rule = new UniqueEntrySlug('article');
    
    $validator = Validator::make(
        ['slug' => 'deleted-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('passes for empty slug', function () {
    $rule = new UniqueEntrySlug('article');
    
    $validator = Validator::make(
        ['slug' => ''],
        ['slug' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails if post type does not exist', function () {
    $rule = new UniqueEntrySlug('non-existent-type');
    
    $validator = Validator::make(
        ['slug' => 'test-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('slug'))->toContain('post type does not exist');
});

test('works with numeric post type slug', function () {
    $numericType = PostType::factory()->create(['slug' => '2024']);
    
    $rule = new UniqueEntrySlug('2024');
    
    $validator = Validator::make(
        ['slug' => 'test-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

