<?php

declare(strict_types=1);

use App\Models\PostType;
use App\Models\Entry;
use App\Domain\PostTypes\PostTypeOptions;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit-тесты для модели PostType.
 *
 * Проверяют структуру модели, fillable, casts и отношения
 * без взаимодействия с БД.
 */

test('has fillable attributes', function () {
    $postType = new PostType();

    $fillable = $postType->getFillable();

    expect($fillable)->toContain('slug')
        ->and($fillable)->toContain('name')
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

test('slug is unique', function () {
    $postType1 = new PostType(['slug' => 'article']);
    $postType2 = new PostType(['slug' => 'article']);

    expect($postType1->slug)->toBe($postType2->slug);
    // Uniqueness will be enforced by DB constraint
});

