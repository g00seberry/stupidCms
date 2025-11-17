<?php

declare(strict_types=1);

use App\Domain\Entries\PublishingService;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Feature-тесты для PublishingService с реальной БД.
 */

beforeEach(function () {
    $this->service = new PublishingService();
    $this->user = User::factory()->create();
    $this->postType = PostType::factory()->create();
});

test('entry can be published', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'draft',
        'published_at' => null,
    ]);

    $payload = ['status' => 'published'];
    $result = $this->service->applyAndValidate($payload, $entry);

    $entry->update($result);

    expect($entry->fresh()->status)->toBe('published')
        ->and($entry->fresh()->published_at)->not->toBeNull();
});

test('entry can be scheduled for publishing in past', function () {
    $pastDate = Carbon::now('UTC')->subHour();
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'draft',
        'published_at' => null,
    ]);

    $payload = [
        'status' => 'published',
        'published_at' => $pastDate,
    ];
    
    $result = $this->service->applyAndValidate($payload, $entry);
    $entry->update($result);

    $freshEntry = $entry->fresh();
    
    expect($freshEntry->status)->toBe('published')
        ->and($freshEntry->published_at)->not->toBeNull()
        ->and($freshEntry->published_at->format('Y-m-d H:i:s'))->toBe($pastDate->format('Y-m-d H:i:s'));
});

test('cannot publish with future date', function () {
    $futureDate = Carbon::now('UTC')->addDay();
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $payload = [
        'status' => 'published',
        'published_at' => $futureDate,
    ];

    $this->service->applyAndValidate($payload, $entry);
})->throws(ValidationException::class);

test('entry can be unpublished', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'published',
        'published_at' => Carbon::now('UTC')->subDays(2),
    ]);

    $payload = ['status' => 'draft'];
    $result = $this->service->applyAndValidate($payload, $entry);
    
    $entry->update($result);

    expect($entry->fresh()->status)->toBe('draft')
        ->and($entry->fresh()->published_at)->not->toBeNull(); // Date remains
});

test('multiple entries can be published', function () {
    $entry1 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'draft',
    ]);
    
    $entry2 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $payload1 = ['status' => 'published'];
    $result1 = $this->service->applyAndValidate($payload1, $entry1);
    $entry1->update($result1);

    $payload2 = ['status' => 'published'];
    $result2 = $this->service->applyAndValidate($payload2, $entry2);
    $entry2->update($result2);

    expect($entry1->fresh()->status)->toBe('published')
        ->and($entry2->fresh()->status)->toBe('published')
        ->and($entry1->fresh()->published_at)->not->toBeNull()
        ->and($entry2->fresh()->published_at)->not->toBeNull();
});

test('published_at uses UTC timezone', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $payload = ['status' => 'published'];
    $result = $this->service->applyAndValidate($payload, $entry);
    $entry->update($result);

    expect($entry->fresh()->published_at->timezone->getName())->toBe('UTC');
});

