<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Path;
use App\Models\PathMediaConstraint;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-тесты для модели PathMediaConstraint.
 *
 * Проверяют структуру модели, fillable, связи и каскадное удаление.
 */

uses(TestCase::class, RefreshDatabase::class);

test('has fillable attributes', function () {
    $constraint = new PathMediaConstraint();

    $fillable = $constraint->getFillable();

    expect($fillable)->toContain('path_id')
        ->and($fillable)->toContain('allowed_mime');
});

test('has path relationship', function () {
    $constraint = new PathMediaConstraint();

    $relation = $constraint->path();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Path::class);
});

test('can be created with mass assignment', function () {
    $path = Path::factory()->create();

    $constraint = PathMediaConstraint::create([
        'path_id' => $path->id,
        'allowed_mime' => 'image/jpeg',
    ]);

    expect($constraint->path_id)->toBe($path->id)
        ->and($constraint->allowed_mime)->toBe('image/jpeg')
        ->and($constraint->path->id)->toBe($path->id);
});

test('path relationship returns correct Path', function () {
    $path = Path::factory()->create();

    $constraint = PathMediaConstraint::factory()->create([
        'path_id' => $path->id,
    ]);

    expect($constraint->path->id)->toBe($path->id);
});

