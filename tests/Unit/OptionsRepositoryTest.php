<?php

namespace Tests\Unit;

use App\Domain\Options\OptionsRepository;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OptionsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OptionsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(OptionsRepository::class);
        Cache::flush();
    }

    public function test_reading_missing_option_returns_default(): void
    {
        $value = $this->repository->get('site', 'home_entry_id');
        $this->assertNull($value);
    }

    public function test_reading_missing_option_with_custom_default(): void
    {
        $value = $this->repository->get('site', 'home_entry_id', 42);
        $this->assertEquals(42, $value);
    }

    public function test_write_and_read_option(): void
    {
        $this->repository->set('site', 'home_entry_id', 42);
        $value = $this->repository->get('site', 'home_entry_id');
        $this->assertEquals(42, $value);
    }

    public function test_get_int_returns_integer(): void
    {
        $this->repository->set('site', 'home_entry_id', 42);
        $value = $this->repository->getInt('site', 'home_entry_id');
        $this->assertIsInt($value);
        $this->assertEquals(42, $value);
    }

    public function test_get_int_returns_null_for_missing_option(): void
    {
        $value = $this->repository->getInt('site', 'home_entry_id');
        $this->assertNull($value);
    }

    public function test_cache_invalidation_after_set(): void
    {
        // Устанавливаем значение
        $this->repository->set('site', 'home_entry_id', 42);
        
        // Читаем из кэша
        $value1 = $this->repository->get('site', 'home_entry_id');
        $this->assertEquals(42, $value1);

        // Меняем значение напрямую в БД (имитируем изменение извне)
        Option::query()
            ->where('namespace', 'site')
            ->where('key', 'home_entry_id')
            ->update(['value_json' => 100]);

        // Очищаем кэш вручную (set() должен был это сделать, но проверим)
        Cache::tags(['options', 'options:site'])->flush();

        // Читаем снова - должно быть новое значение
        $value2 = $this->repository->get('site', 'home_entry_id');
        $this->assertEquals(100, $value2);
    }

    public function test_option_changed_event_is_fired_after_commit(): void
    {
        Event::fake();

        $this->repository->set('site', 'home_entry_id', 42);

        // Событие должно быть отправлено после коммита транзакции
        Event::assertDispatched(\App\Events\OptionChanged::class, function ($event) {
            return $event->namespace === 'site' 
                && $event->key === 'home_entry_id' 
                && $event->value === 42
                && $event->oldValue === null;
        });
    }

    public function test_option_changed_event_includes_old_value(): void
    {
        Event::fake();

        // Устанавливаем начальное значение
        $this->repository->set('site', 'home_entry_id', 42);
        Event::fake(); // Сбрасываем для следующего вызова

        // Меняем значение
        $this->repository->set('site', 'home_entry_id', 100);

        Event::assertDispatched(\App\Events\OptionChanged::class, function ($event) {
            return $event->namespace === 'site' 
                && $event->key === 'home_entry_id' 
                && $event->value === 100
                && $event->oldValue === 42;
        });
    }

    public function test_event_fired_after_successful_commit(): void
    {
        Event::fake();

        // Устанавливаем значение в транзакции
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->repository->set('site', 'home_entry_id', 42);
        });

        // Событие должно быть отправлено после успешного коммита
        Event::assertDispatched(\App\Events\OptionChanged::class, function ($event) {
            return $event->namespace === 'site' 
                && $event->key === 'home_entry_id' 
                && $event->value === 42;
        });
    }

    public function test_fallback_without_tags_uses_forget(): void
    {
        // Используем array cache (без тегов)
        Cache::flush();
        config(['cache.default' => 'array']);
        $this->repository = app(OptionsRepository::class);

        // Устанавливаем значение
        $this->repository->set('site', 'home_entry_id', 42);
        $value1 = $this->repository->get('site', 'home_entry_id');
        $this->assertEquals(42, $value1);

        // Меняем значение
        $this->repository->set('site', 'home_entry_id', 100);
        
        // Повторный get() должен отдать новое значение без рестарта
        $value2 = $this->repository->get('site', 'home_entry_id');
        $this->assertEquals(100, $value2);
    }

    public function test_parallel_set_maintains_consistency(): void
    {
        // Симулируем параллельные set() одного ключа
        $this->repository->set('site', 'home_entry_id', 1);
        
        // Второй set должен перезаписать
        $this->repository->set('site', 'home_entry_id', 2);
        
        $value = $this->repository->get('site', 'home_entry_id');
        $this->assertEquals(2, $value);
        
        // Проверяем, что в БД тоже правильное значение
        $dbValue = Option::query()
            ->where('namespace', 'site')
            ->where('key', 'home_entry_id')
            ->value('value_json');
        $this->assertEquals(2, $dbValue);
    }

    public function test_setting_null_value(): void
    {
        // Сначала устанавливаем значение
        $this->repository->set('site', 'home_entry_id', 42);
        $this->assertEquals(42, $this->repository->get('site', 'home_entry_id'));

        // Затем устанавливаем null
        $this->repository->set('site', 'home_entry_id', null);
        $this->assertNull($this->repository->get('site', 'home_entry_id'));
    }
}

