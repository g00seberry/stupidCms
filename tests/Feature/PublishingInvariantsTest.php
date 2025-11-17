<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Domain\Entries\PublishingService;
use App\Models\PostType;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Support\FeatureTestCase;

class PublishingInvariantsTest extends FeatureTestCase
{
    private PostType $postType;
    private PublishingService $publishingService;

    protected function setUp(): void
    {
        parent::setUp();

        // Фиксируем время для стабильности тестов
        Carbon::setTestNow('2025-01-01 12:00:00');

        $this->postType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);

        $this->publishingService = new PublishingService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_draft_without_date_creates_successfully(): void
    {
        $payload = [
            'post_type_id' => $this->postType->id,
            'title' => 'Draft Entry',
            'slug' => 'draft-entry',
            'status' => 'draft',
            'published_at' => null,
            'data_json' => [],
        ];

        $processed = $this->publishingService->applyAndValidate($payload);
        $entry = Entry::create($processed);

        $this->assertEquals('draft', $entry->status);
        $this->assertNull($entry->published_at);
        $this->assertFalse(Entry::published()->where('id', $entry->id)->exists());
    }

    public function test_publish_without_date_auto_fills_and_entry_is_published(): void
    {
        $payload = [
            'post_type_id' => $this->postType->id,
            'title' => 'Published Entry',
            'slug' => 'published-entry',
            'status' => 'published',
            'data_json' => [],
        ];

        $before = Carbon::now('UTC');
        $processed = $this->publishingService->applyAndValidate($payload);
        $after = Carbon::now('UTC');

        $entry = Entry::create($processed);

        $this->assertEquals('published', $entry->status);
        $this->assertNotNull($entry->published_at);
        $publishedAt = Carbon::parse($entry->published_at)->setTimezone('UTC');
        $this->assertTrue($publishedAt->gte($before->copy()->subSecond()) && $publishedAt->lte($after->copy()->addSecond()));

        // Проверяем, что запись попадает в scopePublished
        $this->assertTrue(Entry::published()->where('id', $entry->id)->exists());
    }

    public function test_publish_with_past_date_is_successful(): void
    {
        $pastDate = Carbon::now('UTC')->subDay();

        $payload = [
            'post_type_id' => $this->postType->id,
            'title' => 'Past Entry',
            'slug' => 'past-entry',
            'status' => 'published',
            'published_at' => $pastDate->toDateTimeString(),
            'data_json' => [],
        ];

        $processed = $this->publishingService->applyAndValidate($payload);
        $entry = Entry::create($processed);

        $this->assertEquals('published', $entry->status);
        $entryPublishedAt = Carbon::parse($entry->published_at)->setTimezone('UTC');
        $this->assertEquals($pastDate->format('Y-m-d H:i:s'), $entryPublishedAt->format('Y-m-d H:i:s'));
        $this->assertTrue(Entry::published()->where('id', $entry->id)->exists());
    }

    public function test_publish_with_future_date_throws_validation_exception(): void
    {
        $futureDate = Carbon::now('UTC')->addDay();

        $payload = [
            'post_type_id' => $this->postType->id,
            'title' => 'Future Entry',
            'slug' => 'future-entry',
            'status' => 'published',
            'published_at' => $futureDate->toDateTimeString(),
            'data_json' => [],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Дата публикации не может быть в будущем для статуса "published"');

        $this->publishingService->applyAndValidate($payload);
    }

    public function test_update_published_entry_without_date_change_is_allowed(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Original',
            'slug' => 'original',
            'status' => 'published',
            'published_at' => Carbon::now('UTC')->subDay(),
            'data_json' => [],
        ]);

        $originalPublishedAt = $entry->published_at;

        $payload = [
            'title' => 'Updated Title',
        ];

        $processed = $this->publishingService->applyAndValidate($payload, $entry);
        $entry->update($processed);

        $this->assertEquals('Updated Title', $entry->title);
        $this->assertTrue($entry->published_at->equalTo($originalPublishedAt));
    }

    public function test_update_published_entry_with_future_date_throws_exception(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Original',
            'slug' => 'original',
            'status' => 'published',
            'published_at' => Carbon::now('UTC')->subDay(),
            'data_json' => [],
        ]);

        $futureDate = Carbon::now('UTC')->addDay();

        $payload = [
            'status' => 'published',
            'published_at' => $futureDate->toDateTimeString(),
        ];

        $this->expectException(ValidationException::class);

        $this->publishingService->applyAndValidate($payload, $entry);
    }

    public function test_unpublish_removes_from_published_scope(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Published',
            'slug' => 'published',
            'status' => 'published',
            'published_at' => Carbon::now('UTC')->subDay(),
            'data_json' => [],
        ]);

        $this->assertTrue(Entry::published()->where('id', $entry->id)->exists());

        $payload = [
            'status' => 'draft',
        ];

        $processed = $this->publishingService->applyAndValidate($payload, $entry);
        $entry->update($processed);

        $this->assertEquals('draft', $entry->status);
        $this->assertFalse(Entry::published()->where('id', $entry->id)->exists());
    }

    public function test_scope_published_includes_entry_with_exact_now(): void
    {
        $now = Carbon::now('UTC');

        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Now Entry',
            'slug' => 'now-entry',
            'status' => 'published',
            'published_at' => $now,
            'data_json' => [],
        ]);

        // Небольшая задержка для проверки граничного случая
        usleep(1000);

        $this->assertTrue(Entry::published()->where('id', $entry->id)->exists());
    }

    public function test_scope_published_excludes_future_entry(): void
    {
        $futureDate = Carbon::now('UTC')->addDay();

        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Future Entry',
            'slug' => 'future-entry',
            'status' => 'published',
            'published_at' => $futureDate,
            'data_json' => [],
        ]);

        $this->assertFalse(Entry::published()->where('id', $entry->id)->exists());
    }

    public function test_scope_published_excludes_draft_entry(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Draft Entry',
            'slug' => 'draft-entry',
            'status' => 'draft',
            'published_at' => Carbon::now('UTC')->subDay(),
            'data_json' => [],
        ]);

        $this->assertFalse(Entry::published()->where('id', $entry->id)->exists());
    }

    public function test_scope_published_excludes_entry_with_null_published_at(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Null Date Entry',
            'slug' => 'null-date-entry',
            'status' => 'published',
            'published_at' => null,
            'data_json' => [],
        ]);

        $this->assertFalse(Entry::published()->where('id', $entry->id)->exists());
    }
}

