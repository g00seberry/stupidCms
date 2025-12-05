<?php

declare(strict_types=1);

use App\Models\PostType;
use App\Models\Entry;
use App\Domain\PostTypes\PostTypeOptions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-тесты для модели PostType.
 *
 * Проверяют структуру модели, fillable, casts и отношения.
 */

uses(TestCase::class, RefreshDatabase::class);

test('has fillable attributes', function () {
    $postType = new PostType();

    $fillable = $postType->getFillable();

    expect($fillable)->toContain('name')
        ->and($fillable)->toContain('template')
        ->and($fillable)->toContain('options_json');
});

test('casts options_json to PostTypeOptions', function () {
    $postType = new PostType();

    $casts = $postType->getCasts();

    expect($casts)->toHaveKey('options_json')
        ->and($casts['options_json'])->toBe(\App\Casts\AsPostTypeOptions::class);
});

test('has entries relationship', function () {
    $postType = new PostType();

    $relation = $postType->entries();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Entry::class);
});

test('template can be set', function () {
    $postType1 = new PostType(['template' => 'templates.article']);
    $postType2 = new PostType(['template' => 'templates.article']);

    expect($postType1->template)->toBe($postType2->template);
});

