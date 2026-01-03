<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Path;
use App\Models\PathMediaConstraint;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-тесты для связи mediaConstraints() и хелперов модели Path.
 *
 * Проверяют связь с PathMediaConstraint и вспомогательные методы.
 */

uses(TestCase::class, RefreshDatabase::class);

test('Path has mediaConstraints relationship', function () {
    $path = new Path();

    $relation = $path->mediaConstraints();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(PathMediaConstraint::class);
});

test('mediaConstraints() returns related constraints', function () {
    $path = Path::factory()->create(['data_type' => 'media']);

    $constraint1 = PathMediaConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_mime' => 'image/jpeg',
    ]);

    $constraint2 = PathMediaConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_mime' => 'image/png',
    ]);

    // Создаём constraint для другого path, чтобы убедиться, что связь работает правильно
    $otherPath = Path::factory()->create(['data_type' => 'media']);
    PathMediaConstraint::factory()->create([
        'path_id' => $otherPath->id,
        'allowed_mime' => 'image/jpeg',
    ]);

    $constraints = $path->mediaConstraints;

    expect($constraints)->toHaveCount(2)
        ->and($constraints->pluck('id')->toArray())->toContain($constraint1->id, $constraint2->id);
});

test('getAllowedMimeTypes() returns array of MIME types', function () {
    $path = Path::factory()->create(['data_type' => 'media']);

    PathMediaConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_mime' => 'image/jpeg',
    ]);

    PathMediaConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_mime' => 'image/png',
    ]);

    $mimeTypes = $path->getAllowedMimeTypes();

    expect($mimeTypes)->toHaveCount(2)
        ->and($mimeTypes)->toContain('image/jpeg', 'image/png');
});

test('hasMediaConstraints() returns true when constraints exist', function () {
    $path = Path::factory()->create(['data_type' => 'media']);

    PathMediaConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_mime' => 'image/jpeg',
    ]);

    expect($path->hasMediaConstraints())->toBeTrue();
});

test('hasMediaConstraints() returns false when no constraints', function () {
    $path = Path::factory()->create(['data_type' => 'media']);

    expect($path->hasMediaConstraints())->toBeFalse();
});

