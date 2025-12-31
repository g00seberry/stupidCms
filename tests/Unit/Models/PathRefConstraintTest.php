<?php

declare(strict_types=1);

use App\Models\Path;
use App\Models\PathRefConstraint;
use App\Models\PostType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-тесты для модели PathRefConstraint.
 *
 * Проверяют структуру модели, fillable, связи и каскадное удаление.
 */

uses(TestCase::class, RefreshDatabase::class);

test('has fillable attributes', function () {
    $constraint = new PathRefConstraint();

    $fillable = $constraint->getFillable();

    expect($fillable)->toContain('path_id')
        ->and($fillable)->toContain('allowed_post_type_id');
});

test('has path relationship', function () {
    $constraint = new PathRefConstraint();

    $relation = $constraint->path();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Path::class);
});

test('has allowedPostType relationship', function () {
    $constraint = new PathRefConstraint();

    $relation = $constraint->allowedPostType();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(PostType::class);
});

test('can be created with mass assignment', function () {
    $path = Path::factory()->create();
    $postType = PostType::factory()->create();

    $constraint = PathRefConstraint::create([
        'path_id' => $path->id,
        'allowed_post_type_id' => $postType->id,
    ]);

    expect($constraint->path_id)->toBe($path->id)
        ->and($constraint->allowed_post_type_id)->toBe($postType->id)
        ->and($constraint->path->id)->toBe($path->id)
        ->and($constraint->allowedPostType->id)->toBe($postType->id);
});

test('path relationship returns correct Path', function () {
    $path = Path::factory()->create();
    $postType = PostType::factory()->create();

    $constraint = PathRefConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_post_type_id' => $postType->id,
    ]);

    expect($constraint->path)->not->toBeNull()
        ->and($constraint->path->id)->toBe($path->id)
        ->and($constraint->path->name)->toBe($path->name);
});

test('allowedPostType relationship returns correct PostType', function () {
    $path = Path::factory()->create();
    $postType = PostType::factory()->create();

    $constraint = PathRefConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_post_type_id' => $postType->id,
    ]);

    expect($constraint->allowedPostType)->not->toBeNull()
        ->and($constraint->allowedPostType->id)->toBe($postType->id)
        ->and($constraint->allowedPostType->name)->toBe($postType->name);
});

test('cascades delete when Path is deleted', function () {
    $path = Path::factory()->create();
    $postType = PostType::factory()->create();

    $constraint = PathRefConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_post_type_id' => $postType->id,
    ]);

    expect(PathRefConstraint::count())->toBe(1);

    // Удаляем Path - constraint должен удалиться каскадно
    $path->delete();

    expect(PathRefConstraint::count())->toBe(0);
    $this->assertDatabaseMissing('path_ref_constraints', [
        'id' => $constraint->id,
    ]);
});

test('restricts delete when PostType is deleted', function () {
    $path = Path::factory()->create();
    $postType = PostType::factory()->create();

    $constraint = PathRefConstraint::factory()->create([
        'path_id' => $path->id,
        'allowed_post_type_id' => $postType->id,
    ]);

    // Попытка удалить PostType должна вызвать исключение из-за restrictOnDelete
    expect(fn () => $postType->delete())->toThrow(\Illuminate\Database\QueryException::class);

    // Constraint должен остаться
    expect(PathRefConstraint::count())->toBe(1);
    $this->assertDatabaseHas('path_ref_constraints', [
        'id' => $constraint->id,
    ]);
});

