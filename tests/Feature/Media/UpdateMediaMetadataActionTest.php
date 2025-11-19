<?php

declare(strict_types=1);

use App\Domain\Media\Actions\UpdateMediaMetadataAction;
use App\Models\Media;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Feature-тесты для UpdateMediaMetadataAction.
 */

test('updates media metadata', function () {
    $action = app(UpdateMediaMetadataAction::class);
    
    $media = Media::factory()->create([
        'title' => 'Old Title',
        'alt' => 'Old Alt',
    ]);

    $updated = $action->execute($media->id, [
        'title' => 'New Title',
        'alt' => 'New Alt',
    ]);

    expect($updated->title)->toBe('New Title')
        ->and($updated->alt)->toBe('New Alt');

    $this->assertDatabaseHas('media', [
        'id' => $media->id,
        'title' => 'New Title',
        'alt' => 'New Alt',
    ]);
});

test('updates only title', function () {
    $action = app(UpdateMediaMetadataAction::class);
    
    $media = Media::factory()->create([
        'title' => 'Old Title',
        'alt' => 'Original Alt',
    ]);

    $updated = $action->execute($media->id, [
        'title' => 'Updated Title',
    ]);

    expect($updated->title)->toBe('Updated Title')
        ->and($updated->alt)->toBe('Original Alt');
});

test('updates only alt text', function () {
    $action = app(UpdateMediaMetadataAction::class);
    
    $media = Media::factory()->create([
        'title' => 'Original Title',
        'alt' => 'Old Alt',
    ]);

    $updated = $action->execute($media->id, [
        'alt' => 'Updated Alt',
    ]);

    expect($updated->title)->toBe('Original Title')
        ->and($updated->alt)->toBe('Updated Alt');
});

test('can update soft deleted media', function () {
    $action = app(UpdateMediaMetadataAction::class);
    
    $media = Media::factory()->create([
        'title' => 'Old Title',
    ]);
    
    $media->delete(); // Soft delete

    $updated = $action->execute($media->id, [
        'title' => 'Updated Title',
    ]);

    expect($updated->title)->toBe('Updated Title')
        ->and($updated->trashed())->toBeTrue();
});

test('throws exception for non existent media', function () {
    $action = app(UpdateMediaMetadataAction::class);

    $action->execute('01JCQZR0G0000000000000ZZZZ', [
        'title' => 'New Title',
    ]);
})->throws(ModelNotFoundException::class);

test('clears metadata when set to null', function () {
    $action = app(UpdateMediaMetadataAction::class);
    
    $media = Media::factory()->create([
        'title' => 'Original Title',
        'alt' => 'Original Alt',
    ]);

    $updated = $action->execute($media->id, [
        'title' => null,
        'alt' => null,
    ]);

    expect($updated->title)->toBeNull()
        ->and($updated->alt)->toBeNull();
});

test('returns fresh model instance', function () {
    $action = app(UpdateMediaMetadataAction::class);
    
    $media = Media::factory()->create([
        'title' => 'Old Title',
    ]);

    $updated = $action->execute($media->id, [
        'title' => 'New Title',
    ]);

    // Verify it's a fresh instance by checking title is updated
    expect($updated->title)->toBe('New Title')
        ->and($updated->id)->toBe($media->id)
        ->and($updated->wasRecentlyCreated)->toBeFalse();
    
    // Original model should still have old title
    expect($media->title)->toBe('Old Title');
});

