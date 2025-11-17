<?php

declare(strict_types=1);

use App\Domain\Entries\PublishingService;
use App\Models\Entry;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Unit-тесты для PublishingService.
 */

beforeEach(function () {
    $this->service = new PublishingService();
});

test('publishes entry immediately with auto published_at', function () {
    $payload = ['status' => 'published'];

    $result = $this->service->applyAndValidate($payload, null);

    expect($result)->toHaveKey('published_at')
        ->and($result['published_at'])->toBeInstanceOf(Carbon::class);
});

test('schedules entry with provided published_at', function () {
    $publishedAt = Carbon::now('UTC')->subHour(); // Past time
    $payload = [
        'status' => 'published',
        'published_at' => $publishedAt,
    ];

    $result = $this->service->applyAndValidate($payload, null);

    expect($result['published_at'])->toBe($publishedAt);
});

test('validates published_at is not in future', function () {
    $futureDate = Carbon::now('UTC')->addDay();
    $payload = [
        'status' => 'published',
        'published_at' => $futureDate,
    ];

    $this->service->applyAndValidate($payload, null);
})->throws(ValidationException::class);

test('changes entry status to published with auto date', function () {
    $entry = new Entry([
        'status' => 'draft',
        'published_at' => null,
    ]);

    $payload = ['status' => 'published'];

    $result = $this->service->applyAndValidate($payload, $entry);

    expect($result)->toHaveKey('published_at')
        ->and($result['published_at'])->toBeInstanceOf(Carbon::class);
});

test('overwritespublished_at when transitioning draft to published without explicit date', function () {
    $originalDate = Carbon::now('UTC')->subDays(5);
    $entry = new Entry();
    $entry->status = 'draft';
    $entry->published_at = $originalDate; // Old date set

    $payload = ['status' => 'published']; // No explicit published_at in payload

    $result = $this->service->applyAndValidate($payload, $entry);

    // Service auto-sets published_at on draft->published transition, even if already exists
    expect($result)->toHaveKey('published_at')
        ->and($result['published_at'])->toBeInstanceOf(Carbon::class)
        ->and($result['published_at']->gt($originalDate))->toBeTrue();
});

test('keeps draft status without published_at', function () {
    $payload = ['status' => 'draft'];

    $result = $this->service->applyAndValidate($payload, null);

    expect($result)->not->toHaveKey('published_at');
});

test('allows updating published entry without changing published_at', function () {
    $originalDate = Carbon::now('UTC')->subDays(3);
    $entry = new Entry([
        'status' => 'published',
        'published_at' => $originalDate,
    ]);
    $entry->published_at = $originalDate;

    $payload = ['status' => 'published', 'title' => 'Updated Title'];

    $result = $this->service->applyAndValidate($payload, $entry);

    expect($result)->not->toHaveKey('published_at')
        ->and($result['title'])->toBe('Updated Title');
});

test('sets published_at when creating published entry', function () {
    $payload = ['status' => 'published', 'title' => 'New Entry'];

    $result = $this->service->applyAndValidate($payload, null);

    expect($result)->toHaveKey('published_at')
        ->and($result['published_at'])->toBeInstanceOf(Carbon::class)
        ->and($result['published_at']->lte(Carbon::now('UTC')))->toBeTrue();
});

test('transitions from draft to published sets published_at', function () {
    $entry = new Entry(['status' => 'draft']);

    $payload = ['status' => 'published'];

    $result = $this->service->applyAndValidate($payload, $entry);

    expect($result)->toHaveKey('published_at')
        ->and($result['published_at'])->toBeInstanceOf(Carbon::class);
});

test('validates published_at when explicitly provided', function () {
    $pastDate = Carbon::now('UTC')->subHour();
    $payload = [
        'status' => 'published',
        'published_at' => $pastDate,
    ];

    $result = $this->service->applyAndValidate($payload, null);

    expect($result['published_at'])->toBe($pastDate);
});

