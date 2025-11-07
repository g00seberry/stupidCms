<?php

namespace Tests\Unit;

use App\Models\Entry;
use App\Models\PostType;
use App\Support\Publishing\PublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PublishingService $service;
    private PostType $postType;

    protected function setUp(): void
    {
        parent::setUp();

        // Фиксируем время для стабильности тестов
        Carbon::setTestNow('2025-01-01 12:00:00');

        $this->service = new PublishingService();

        // Создаём PostType для тестов
        $this->postType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_draft_without_date_is_allowed(): void
    {
        $payload = [
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'published_at' => null,
            'data_json' => [],
        ];

        $result = $this->service->applyAndValidate($payload);

        $this->assertNull($result['published_at']);
        $this->assertEquals('draft', $result['status']);
    }

    public function test_publish_without_date_auto_fills_now(): void
    {
        $payload = [
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'data_json' => [],
        ];

        $before = Carbon::now('UTC');
        $result = $this->service->applyAndValidate($payload);
        $after = Carbon::now('UTC');

        $this->assertNotNull($result['published_at']);
        $publishedAt = Carbon::parse($result['published_at'], 'UTC');
        $this->assertTrue($publishedAt->gte($before) && $publishedAt->lte($after));
    }

    public function test_publish_with_past_date_is_allowed(): void
    {
        $pastDate = Carbon::now('UTC')->subDay();

        $payload = [
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => $pastDate->toDateTimeString(),
            'data_json' => [],
        ];

        $result = $this->service->applyAndValidate($payload);

        $this->assertEquals($pastDate->toDateTimeString(), $result['published_at']);
    }

    public function test_publish_with_future_date_throws_exception(): void
    {
        $futureDate = Carbon::now('UTC')->addDay();

        $payload = [
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => $futureDate->toDateTimeString(),
            'data_json' => [],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('validation.published_at_not_in_future', [], 'ru'));

        $this->service->applyAndValidate($payload);
    }

    public function test_update_published_entry_without_date_change_is_allowed(): void
    {
        $originalDate = Carbon::parse('2024-12-01 12:00:00', 'UTC');
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => $originalDate,
            'data_json' => [],
        ]);

        $payload = [
            'title' => 'Updated Title',
        ];

        $result = $this->service->applyAndValidate($payload, $entry);

        // published_at не должен измениться (не должен быть в payload)
        $this->assertArrayNotHasKey('published_at', $result);
        $entry->refresh();
        $this->assertEquals($originalDate->format('Y-m-d H:i:s'), $entry->published_at->format('Y-m-d H:i:s'));
    }

    public function test_update_published_entry_with_future_date_throws_exception(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
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

        $this->service->applyAndValidate($payload, $entry);
    }

    public function test_unpublish_allowed_with_any_date(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => Carbon::now('UTC')->subDay(),
            'data_json' => [],
        ]);

        $payload = [
            'status' => 'draft',
            'published_at' => Carbon::now('UTC')->addDay()->toDateTimeString(), // Даже будущая дата разрешена для draft
        ];

        $result = $this->service->applyAndValidate($payload, $entry);

        $this->assertEquals('draft', $result['status']);
    }

    public function test_update_uses_existing_status_when_not_provided(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => Carbon::now('UTC')->subDay(),
            'data_json' => [],
        ]);

        $payload = [
            'title' => 'Updated',
        ];

        $result = $this->service->applyAndValidate($payload, $entry);

        // Статус должен остаться published из существующей записи
        $this->assertArrayNotHasKey('status', $result); // или проверяем что статус не изменился
    }

    public function test_publish_with_exact_now_is_allowed(): void
    {
        $now = Carbon::now('UTC');

        $payload = [
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => $now->toDateTimeString(),
            'data_json' => [],
        ];

        $result = $this->service->applyAndValidate($payload);

        $this->assertNotNull($result['published_at']);
    }

    public function test_update_published_entry_without_published_at_does_not_change_date(): void
    {
        // Имеем status=published, published_at='2024-01-01'
        $originalDate = Carbon::parse('2024-01-01 12:00:00', 'UTC');
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => $originalDate,
            'data_json' => [],
        ]);

        // Делаем PATCH без published_at
        $payload = [
            'title' => 'Updated',
        ];

        $result = $this->service->applyAndValidate($payload, $entry);

        // published_at не должен быть в payload (не изменяется)
        $this->assertArrayNotHasKey('published_at', $result);
        $entry->refresh();
        $this->assertEquals($originalDate->format('Y-m-d H:i:s'), $entry->published_at->format('Y-m-d H:i:s'));
    }

    public function test_transition_draft_to_published_without_date_auto_fills_now(): void
    {
        // Был draft с любой/NULL датой
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'published_at' => null,
            'data_json' => [],
        ]);

        // Меняем на published без published_at
        $payload = [
            'status' => 'published',
        ];

        $result = $this->service->applyAndValidate($payload, $entry);

        // Авто now() в UTC
        $this->assertNotNull($result['published_at']);
        $publishedAt = Carbon::parse($result['published_at'], 'UTC');
        $this->assertEquals(Carbon::now('UTC')->format('Y-m-d H:i:s'), $publishedAt->format('Y-m-d H:i:s'));
    }

    public function test_boundary_now_passes_validation(): void
    {
        // published_at == now() проходит валидацию
        $now = Carbon::now('UTC');

        $payload = [
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => $now->toDateTimeString(),
            'data_json' => [],
        ];

        $result = $this->service->applyAndValidate($payload);

        $this->assertNotNull($result['published_at']);
        $publishedAt = Carbon::parse($result['published_at'], 'UTC');
        $this->assertTrue($publishedAt->lte($now));
    }

    public function test_update_published_entry_keeps_historical_date(): void
    {
        // Уже опубликованная запись с исторической датой
        $historicalDate = Carbon::parse('2024-06-15 10:30:00', 'UTC');
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => $historicalDate,
            'data_json' => [],
        ]);

        // Обновляем только title, не трогая published_at
        $payload = [
            'title' => 'New Title',
        ];

        $result = $this->service->applyAndValidate($payload, $entry);

        // Историческая дата не должна быть перезаписана
        $this->assertArrayNotHasKey('published_at', $result);
        $entry->refresh();
        $this->assertEquals($historicalDate->format('Y-m-d H:i:s'), $entry->published_at->format('Y-m-d H:i:s'));
    }
}

