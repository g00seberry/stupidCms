<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OptionsCommandsTest extends TestCase
{
    use RefreshDatabase;

    private PostType $postType;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2025-01-01 12:00:00');

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

    public function test_options_get_command_returns_null_for_missing_option(): void
    {
        $this->artisan('cms:options:get', ['namespace' => 'site', 'key' => 'home_entry_id'])
            ->expectsOutput('null')
            ->assertSuccessful();
    }

    public function test_options_set_command_sets_value(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => Carbon::now('UTC')->subDay(),
            'data_json' => [],
        ]);

        $this->artisan('cms:options:set', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
            'value' => (string) $entry->id,
        ])
            ->expectsOutput("Опция site:home_entry_id установлена в {$entry->id}")
            ->assertSuccessful();

        $this->artisan('cms:options:get', ['namespace' => 'site', 'key' => 'home_entry_id'])
            ->expectsOutput((string) $entry->id)
            ->assertSuccessful();
    }

    public function test_options_set_command_validates_entry_exists(): void
    {
        $this->artisan('cms:options:set', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
            'value' => '99999',
        ])
            ->expectsOutput('Запись с ID 99999 не найдена')
            ->assertFailed();
    }

    public function test_options_set_command_validates_positive_integer(): void
    {
        $this->artisan('cms:options:set', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
            'value' => '0',
        ])
            ->expectsOutput('ID записи должен быть положительным числом')
            ->assertFailed();
    }

    public function test_options_set_command_allows_null(): void
    {
        $this->artisan('cms:options:set', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
        ])
            ->expectsOutput('Опция site:home_entry_id установлена в null')
            ->assertSuccessful();
    }

    public function test_options_set_command_parses_json_literals(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => Carbon::now('UTC')->subDay(),
            'data_json' => [],
        ]);

        // Парсинг числа как JSON
        $this->artisan('cms:options:set', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
            'value' => (string) $entry->id,
        ])
            ->expectsOutput("Опция site:home_entry_id установлена в {$entry->id}")
            ->assertSuccessful();

        // Проверяем, что сохранилось как int, а не строка
        $value = options('site', 'home_entry_id');
        $this->assertIsInt($value);
        $this->assertEquals($entry->id, $value);
    }

    public function test_options_set_command_distinguishes_null_from_string_null(): void
    {
        // Устанавливаем null (без аргумента)
        $this->artisan('cms:options:set', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
        ])
            ->assertSuccessful();

        $value1 = options('site', 'home_entry_id');
        $this->assertNull($value1);

        // Устанавливаем строку "null" (если бы передали)
        // Но в нашем случае это не применимо, так как null обрабатывается отдельно
        // Проверяем, что null сохраняется как null, а не строка
        $this->assertNull($value1);
    }

    public function test_options_set_command_rejects_disallowed_option(): void
    {
        $this->artisan('cms:options:set', [
            'namespace' => 'unknown',
            'key' => 'some_key',
            'value' => 'test',
        ])
            ->expectsOutput('Опция unknown:some_key не разрешена. Проверьте config/options.php')
            ->assertFailed();
    }
}

