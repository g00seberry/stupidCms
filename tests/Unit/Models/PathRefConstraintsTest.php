<?php

declare(strict_types=1);

use App\Models\Path;
use App\Models\PathRefConstraint;
use App\Models\PostType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-тесты для связи refConstraints() и хелперов модели Path.
 *
 * Проверяют связь с PathRefConstraint и вспомогательные методы.
 */

uses(TestCase::class, RefreshDatabase::class);

test('Path has refConstraints relationship', function () {
    $path = new Path();

    $relation = $path->refConstraints();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(PathRefConstraint::class);
});

test('refConstraints() returns related constraints', function () {
    $path = Path::factory()->create(['data_type' => 'ref']);
    $postType1 = PostType::factory()->create();
    $postType2 = PostType::factory()->create();

    $constraint1 = PathRefConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_post_type_id' => $postType1->id,
    ]);

    $constraint2 = PathRefConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_post_type_id' => $postType2->id,
    ]);

    // Создаём constraint для другого path, чтобы убедиться, что связь работает правильно
    $otherPath = Path::factory()->create(['data_type' => 'ref']);
    PathRefConstraint::factory()->create([
        'path_id' => $otherPath->id,
        'allowed_post_type_id' => $postType1->id,
    ]);

    $constraints = $path->refConstraints;

    expect($constraints)->toHaveCount(2)
        ->and($constraints->pluck('id')->toArray())->toContain($constraint1->id, $constraint2->id);
});

test('getAllowedPostTypeIds() returns array of post type IDs', function () {
    $path = Path::factory()->create(['data_type' => 'ref']);
    $postType1 = PostType::factory()->create();
    $postType2 = PostType::factory()->create();
    $postType3 = PostType::factory()->create();

    PathRefConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_post_type_id' => $postType1->id,
    ]);

    PathRefConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_post_type_id' => $postType2->id,
    ]);

    PathRefConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_post_type_id' => $postType3->id,
    ]);

    $allowedIds = $path->getAllowedPostTypeIds();

    expect($allowedIds)->toBeArray()
        ->and($allowedIds)->toHaveCount(3)
        ->and($allowedIds)->toContain($postType1->id, $postType2->id, $postType3->id);
});

test('getAllowedPostTypeIds() returns empty array when no constraints', function () {
    $path = Path::factory()->create(['data_type' => 'ref']);

    $allowedIds = $path->getAllowedPostTypeIds();

    expect($allowedIds)->toBeArray()
        ->and($allowedIds)->toBeEmpty();
});

test('hasRefConstraints() returns true when constraints exist', function () {
    $path = Path::factory()->create(['data_type' => 'ref']);
    $postType = PostType::factory()->create();

    PathRefConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_post_type_id' => $postType->id,
    ]);

    expect($path->hasRefConstraints())->toBeTrue();
});

test('hasRefConstraints() returns false when no constraints', function () {
    $path = Path::factory()->create(['data_type' => 'ref']);

    expect($path->hasRefConstraints())->toBeFalse();
});

test('eager loading refConstraints prevents N+1 queries', function () {
    $path1 = Path::factory()->create(['data_type' => 'ref']);
    $path2 = Path::factory()->create(['data_type' => 'ref']);
    $postType1 = PostType::factory()->create();
    $postType2 = PostType::factory()->create();

    PathRefConstraint::factory()->create([
        'path_id' => $path1->id,
        'allowed_post_type_id' => $postType1->id,
    ]);

    PathRefConstraint::factory()->create([
        'path_id' => $path1->id,
        'allowed_post_type_id' => $postType2->id,
    ]);

    PathRefConstraint::factory()->create([
        'path_id' => $path2->id,
        'allowed_post_type_id' => $postType1->id,
    ]);

    // Проверяем, что без eager loading будет N+1 запрос
    \DB::enableQueryLog();
    $paths = Path::whereIn('id', [$path1->id, $path2->id])->get();
    foreach ($paths as $path) {
        $path->refConstraints->count();
    }
    $queriesWithoutEager = count(\DB::getQueryLog());

    // Сбрасываем лог
    \DB::flushQueryLog();
    \DB::enableQueryLog();

    // Проверяем с eager loading
    $pathsWithEager = Path::with('refConstraints')
        ->whereIn('id', [$path1->id, $path2->id])
        ->get();
    foreach ($pathsWithEager as $path) {
        $path->refConstraints->count();
    }
    $queriesWithEager = count(\DB::getQueryLog());

    \DB::disableQueryLog();

    // С eager loading должно быть меньше запросов
    // Без eager: 1 для Path + 2 для каждого Path (refConstraints) = 1 + 2*2 = 5
    // С eager: 1 для Path + 1 для всех refConstraints = 2
    expect($queriesWithEager)->toBeLessThan($queriesWithoutEager);
});

