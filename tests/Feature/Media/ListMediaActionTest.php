<?php

declare(strict_types=1);

use App\Domain\Media\Actions\ListMediaAction;
use App\Domain\Media\MediaQuery;
use App\Domain\Media\MediaDeletedFilter;
use App\Models\Media;

/**
 * Feature-тесты для ListMediaAction.
 */

test('lists media with pagination', function () {
    $action = app(ListMediaAction::class);
    
    Media::factory()->count(5)->create();

    $query = new MediaQuery(
        search: null,
        kind: null,
        mimePrefix: null,
        deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
        sort: 'created_at',
        order: 'desc',
        page: 1,
        perPage: 10
    );

    $result = $action->execute($query);

    expect($result)->toBeInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class)
        ->and($result->total())->toBe(5)
        ->and($result->count())->toBe(5);
});

test('filters media by mime prefix', function () {
    $action = app(ListMediaAction::class);
    
    Media::factory()->create(['mime' => 'image/jpeg']);
    Media::factory()->create(['mime' => 'image/png']);
    Media::factory()->create(['mime' => 'video/mp4']);

    $query = new MediaQuery(
        search: null,
        kind: null,
        mimePrefix: 'image/',
        deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
        sort: 'created_at',
        order: 'desc',
        page: 1,
        perPage: 10
    );

    $result = $action->execute($query);

    expect($result->total())->toBe(2);
});

test('searches media by title and original name', function () {
    $action = app(ListMediaAction::class);
    
    Media::factory()->create(['title' => 'Beautiful Sunset', 'original_name' => 'sunset.jpg']);
    Media::factory()->create(['title' => 'Mountain View', 'original_name' => 'mountain.jpg']);
    Media::factory()->create(['title' => 'Ocean Waves', 'original_name' => 'ocean.jpg']);

    $query = new MediaQuery(
        search: 'sunset',
        kind: null,
        mimePrefix: null,
        deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
        sort: 'created_at',
        order: 'desc',
        page: 1,
        perPage: 10
    );

    $result = $action->execute($query);

    expect($result->total())->toBe(1)
        ->and($result->first()->title)->toBe('Beautiful Sunset');
});

test('excludes soft deleted media by default', function () {
    $action = app(ListMediaAction::class);
    
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();
    $media2->delete(); // Soft delete

    $query = new MediaQuery(
        search: null,
        kind: null,
        mimePrefix: null,
        deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
        sort: 'created_at',
        order: 'desc',
        page: 1,
        perPage: 10
    );

    $result = $action->execute($query);

    expect($result->total())->toBe(1);
});

test('includes soft deleted media when requested', function () {
    $action = app(ListMediaAction::class);
    
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();
    $media2->delete(); // Soft delete

    $query = new MediaQuery(
        search: null,
        kind: null,
        mimePrefix: null,
        deletedFilter: MediaDeletedFilter::WithDeleted,
        sort: 'created_at',
        order: 'desc',
        page: 1,
        perPage: 10
    );

    $result = $action->execute($query);

    expect($result->total())->toBe(2);
});

test('shows only soft deleted media', function () {
    $action = app(ListMediaAction::class);
    
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();
    $media2->delete(); // Soft delete

    $query = new MediaQuery(
        search: null,
        kind: null,
        mimePrefix: null,
        deletedFilter: MediaDeletedFilter::OnlyDeleted,
        sort: 'created_at',
        order: 'desc',
        page: 1,
        perPage: 10
    );

    $result = $action->execute($query);

    expect($result->total())->toBe(1)
        ->and($result->first()->id)->toBe($media2->id);
});

test('sorts media by different fields', function () {
    $action = app(ListMediaAction::class);
    
    Media::factory()->create(['size_bytes' => 100, 'created_at' => now()->subDays(2)]);
    Media::factory()->create(['size_bytes' => 500, 'created_at' => now()->subDays(1)]);
    Media::factory()->create(['size_bytes' => 200, 'created_at' => now()]);

    $query = new MediaQuery(
        search: null,
        kind: null,
        mimePrefix: null,
        deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
        sort: 'size_bytes',
        order: 'desc',
        page: 1,
        perPage: 10
    );

    $result = $action->execute($query);

    expect($result->first()->size_bytes)->toBe(500);
});

test('respects per page limit', function () {
    $action = app(ListMediaAction::class);
    
    Media::factory()->count(25)->create();

    $query = new MediaQuery(
        search: null,
        kind: null,
        mimePrefix: null,
        deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
        sort: 'created_at',
        order: 'desc',
        page: 1,
        perPage: 10
    );

    $result = $action->execute($query);

    expect($result->total())->toBe(25)
        ->and($result->count())->toBe(10)
        ->and($result->lastPage())->toBe(3);
});

