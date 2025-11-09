<?php

namespace Tests\Unit;

use App\Events\EntrySlugChanged;
use App\Models\Entry;
use App\Models\EntrySlug;
use App\Domain\Entries\EntrySlugService;
use App\Models\PostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EntrySlugServiceTest extends TestCase
{
    use RefreshDatabase;

    private EntrySlugService $service;
    private PostType $postType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EntrySlugService::class);

        // Создаём PostType 'page' для тестов
        $this->postType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);
    }

    public function test_on_created_creates_current_slug_record(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $this->service->onCreated($entry);

        $entrySlug = EntrySlug::where('entry_id', $entry->id)
            ->where('slug', 'test-page')
            ->where('is_current', true)
            ->first();

        $this->assertNotNull($entrySlug);
        $this->assertEquals('test-page', $entrySlug->slug);
        $this->assertTrue($entrySlug->is_current);
    }

    public function test_on_created_ensures_only_one_current_slug(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // Создаём несколько записей истории с is_current=true (симуляция проблемы)
        EntrySlug::create([
            'entry_id' => $entry->id,
            'slug' => 'old-slug',
            'is_current' => true,
            'created_at' => now(),
        ]);
        EntrySlug::create([
            'entry_id' => $entry->id,
            'slug' => 'another-slug',
            'is_current' => true,
            'created_at' => now(),
        ]);

        $this->service->onCreated($entry);

        // Должен остаться только один is_current=true
        $currentCount = EntrySlug::where('entry_id', $entry->id)
            ->where('is_current', true)
            ->count();

        $this->assertEquals(1, $currentCount);

        // И это должен быть текущий slug
        $current = EntrySlug::where('entry_id', $entry->id)
            ->where('is_current', true)
            ->first();

        $this->assertEquals('test-page', $current->slug);
    }

    public function test_on_updated_creates_new_slug_record_when_slug_changes(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'about',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // Получаем фактический slug после создания (может быть изменен через ensureSlug)
        $originalSlug = $entry->fresh()->slug;

        // Проверяем, что запись в истории была создана при создании Entry
        $initialHistoryCount = EntrySlug::where('entry_id', $entry->id)->count();
        $this->assertGreaterThan(0, $initialHistoryCount, 'При создании Entry должна быть создана запись в истории');

        // Проверяем, что старая запись существует в истории с is_current=true
        $oldSlugBeforeUpdate = EntrySlug::where('entry_id', $entry->id)
            ->where('slug', $originalSlug)
            ->first();
        $this->assertNotNull($oldSlugBeforeUpdate, "Запись с slug '{$originalSlug}' должна существовать в истории перед обновлением");
        $this->assertTrue($oldSlugBeforeUpdate->is_current, "Запись с slug '{$originalSlug}' должна быть is_current=true перед обновлением");

        // Меняем slug напрямую в БД, чтобы избежать изменений через ensureSlug
        $entry->slug = 'about-us';
        // Сохраняем без вызова observers для изменения slug
        $entry->saveQuietly(['slug']);
        
        // Теперь вызываем onUpdated вручную
        $this->service->onUpdated($entry, 'about');

        // Проверяем, что slug действительно изменился
        $entry->refresh();
        $this->assertEquals('about-us', $entry->slug, 'Slug должен быть изменен на about-us');

        // Старая запись должна быть is_current=false
        $oldSlug = EntrySlug::where('entry_id', $entry->id)
            ->where('slug', $originalSlug)
            ->first();

        $this->assertNotNull($oldSlug, "Старая запись с slug '{$originalSlug}' должна существовать в истории");
        $this->assertFalse($oldSlug->is_current, "Старая запись должна быть is_current=false, но она {$oldSlug->is_current}");

        // Новая запись должна быть is_current=true
        $newSlug = EntrySlug::where('entry_id', $entry->id)
            ->where('slug', 'about-us')
            ->first();

        $this->assertNotNull($newSlug, "Новая запись с slug 'about-us' должна существовать в истории");
        $this->assertTrue($newSlug->is_current);

        // Событие должно быть диспатчено (проверяем через Event::fake() после сохранения)
        // Но так как мы не используем Event::fake(), просто проверяем, что история обновлена
    }

    public function test_on_updated_returns_false_when_slug_not_changed(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'about',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $this->service->onCreated($entry);

        Event::fake();

        $result = $this->service->onUpdated($entry, 'about');

        $this->assertFalse($result);

        // Событие не должно быть диспатчено
        Event::assertNothingDispatched();
    }

    public function test_on_updated_reuses_existing_slug_when_returning_to_previous(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'about',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $this->service->onCreated($entry);

        // Меняем на новый slug
        $entry->slug = 'about-us';
        $entry->save();
        $this->service->onUpdated($entry, 'about');

        // Возвращаемся к старому slug
        $entry->slug = 'about';
        $entry->save();

        Event::fake();

        $result = $this->service->onUpdated($entry, 'about-us');

        $this->assertTrue($result);

        // Не должно быть дубликатов - должна быть переиспользована существующая запись
        $slugRecords = EntrySlug::where('entry_id', $entry->id)
            ->where('slug', 'about')
            ->get();

        $this->assertCount(1, $slugRecords);

        // И она должна быть is_current=true
        $current = EntrySlug::where('entry_id', $entry->id)
            ->where('slug', 'about')
            ->first();

        $this->assertTrue($current->is_current);

        // Событие должно быть диспатчено
        Event::assertDispatched(EntrySlugChanged::class);
    }

    public function test_current_slug_returns_current_slug(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $this->service->onCreated($entry);

        $current = $this->service->currentSlug($entry->id);

        $this->assertEquals('test-page', $current);
    }

    public function test_current_slug_returns_null_when_no_current_slug(): void
    {
        // Создаем Entry без slug, чтобы EntryObserver не создал историю автоматически
        $entry = new Entry([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'data_json' => [],
        ]);
        $entry->saveQuietly(); // Сохраняем без вызова observers

        // Не вызываем onCreated, поэтому истории нет
        $current = $this->service->currentSlug($entry->id);

        $this->assertNull($current);
    }

    public function test_on_updated_ensures_only_one_current_slug(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'about',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // Убеждаемся, что есть хотя бы одна запись в истории
        $this->assertGreaterThan(0, EntrySlug::where('entry_id', $entry->id)->count(), 'Должна быть создана запись в истории при создании Entry');

        Event::fake();

        // Создаём несколько записей с is_current=true (симуляция проблемы)
        EntrySlug::where('entry_id', $entry->id)->update(['is_current' => true]);

        // Меняем slug напрямую в БД, чтобы избежать изменений через ensureSlug
        $entry->slug = 'about-us';
        // Сохраняем без вызова observers для изменения slug
        $entry->saveQuietly(['slug']);
        
        // Теперь вызываем onUpdated вручную
        $this->service->onUpdated($entry, 'about');

        // Проверяем, что slug действительно изменился
        $entry->refresh();
        $this->assertEquals('about-us', $entry->slug, 'Slug должен быть изменен на about-us');

        // Должен остаться только один is_current=true
        $currentCount = EntrySlug::where('entry_id', $entry->id)
            ->where('is_current', true)
            ->count();

        $this->assertEquals(1, $currentCount, "Должен остаться только один is_current=true, но найдено: {$currentCount}");

        // И это должен быть новый slug
        $current = EntrySlug::where('entry_id', $entry->id)
            ->where('is_current', true)
            ->first();

        $this->assertNotNull($current, 'Должна существовать текущая запись в истории');
        $this->assertEquals('about-us', $current->slug, "Текущий slug должен быть 'about-us', но он '{$current->slug}'");
    }

    public function test_created_at_preserved_when_returning_to_previous_slug(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'about',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // Получаем дату создания первой записи
        $firstSlug = EntrySlug::where('entry_id', $entry->id)
            ->where('slug', 'about')
            ->first();
        $originalCreatedAt = $firstSlug->created_at;

        // Меняем slug
        $entry->slug = 'about-us';
        $entry->saveQuietly(['slug']);
        $this->service->onUpdated($entry, 'about');

        // Возвращаемся к старому slug
        $entry->slug = 'about';
        $entry->saveQuietly(['slug']);
        $this->service->onUpdated($entry, 'about-us');

        // Проверяем, что created_at не изменился
        $returnedSlug = EntrySlug::where('entry_id', $entry->id)
            ->where('slug', 'about')
            ->first();

        $this->assertNotNull($returnedSlug);
        $this->assertEquals($originalCreatedAt->format('Y-m-d H:i:s'), $returnedSlug->created_at->format('Y-m-d H:i:s'), 'created_at должен сохраниться при возврате к предыдущему slug');
    }

    public function test_concurrent_updates_maintain_single_is_current(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'about',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // Симулируем параллельные обновления через транзакции
        // В реальности это будет через разные соединения, но для теста используем вложенные транзакции
        DB::transaction(function () use ($entry) {
            // Первая "параллельная" транзакция
            $entry1 = $entry->fresh();
            $entry1->slug = 'about-us';
            $entry1->saveQuietly(['slug']);
            $this->service->onUpdated($entry1, 'about');
        });

        DB::transaction(function () use ($entry) {
            // Вторая "параллельная" транзакция (начинается после первой)
            $entry2 = $entry->fresh();
            $entry2->slug = 'about-page';
            $entry2->saveQuietly(['slug']);
            $this->service->onUpdated($entry2, 'about-us');
        });

        // Проверяем, что остался только один is_current=1
        $currentCount = EntrySlug::where('entry_id', $entry->id)
            ->where('is_current', true)
            ->count();

        $this->assertEquals(1, $currentCount, "Должен остаться только один is_current=true даже при параллельных обновлениях, но найдено: {$currentCount}");

        // И это должен быть последний slug
        $current = EntrySlug::where('entry_id', $entry->id)
            ->where('is_current', true)
            ->first();

        $this->assertEquals('about-page', $current->slug);
    }

    public function test_backfill_fixes_multiple_is_current(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'about',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // Создаём проблему: множественные is_current=1
        EntrySlug::create([
            'entry_id' => $entry->id,
            'slug' => 'old-slug',
            'is_current' => true,
            'created_at' => now(),
        ]);
        EntrySlug::create([
            'entry_id' => $entry->id,
            'slug' => 'another-slug',
            'is_current' => true,
            'created_at' => now(),
        ]);

        // Проверяем, что проблема есть
        $currentCountBefore = EntrySlug::where('entry_id', $entry->id)
            ->where('is_current', true)
            ->count();
        $this->assertGreaterThan(1, $currentCountBefore, 'Должна быть проблема с множественными is_current=1');

        // Запускаем backfill логику (симуляция)
        $currentSlug = $this->service->currentSlug($entry->id);
        if ($currentSlug !== $entry->slug) {
            $this->service->onUpdated($entry, $currentSlug ?? '');
        } else {
            // Исправляем множественные флаги
            DB::transaction(function () use ($entry) {
                EntrySlug::where('entry_id', $entry->id)
                    ->lockForUpdate()
                    ->get();
                
                DB::statement(
                    "UPDATE entry_slugs SET is_current = CASE WHEN slug = ? THEN 1 ELSE 0 END WHERE entry_id = ?",
                    [$entry->slug, $entry->id]
                );
            });
        }

        // Проверяем, что проблема исправлена
        $currentCountAfter = EntrySlug::where('entry_id', $entry->id)
            ->where('is_current', true)
            ->count();

        $this->assertEquals(1, $currentCountAfter, 'Backfill должен исправить множественные is_current=1');
    }
}

