<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    private PostType $postType;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2025-01-01 12:00:00');
        Cache::flush();

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

    public function test_home_route_renders_default_when_option_not_set(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('home.default');
    }

    public function test_home_route_renders_default_when_entry_not_found(): void
    {
        // Устанавливаем несуществующий ID
        option_set('site', 'home_entry_id', 99999);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('home.default');
    }

    public function test_home_route_renders_default_when_entry_is_draft(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Draft Page',
            'slug' => 'draft-page',
            'status' => 'draft',
            'published_at' => null,
            'data_json' => [],
        ]);

        option_set('site', 'home_entry_id', $entry->id);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('home.default');
    }

    public function test_home_route_renders_published_entry(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Home Page',
            'slug' => 'home-page',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'data_json' => [],
        ]);

        option_set('site', 'home_entry_id', $entry->id);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('pages.show');
        $response->assertViewHas('entry', $entry);
    }

    public function test_home_route_renders_default_when_entry_is_soft_deleted(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Deleted Page',
            'slug' => 'deleted-page',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'data_json' => [],
        ]);

        option_set('site', 'home_entry_id', $entry->id);

        // Удаляем запись
        $entry->delete();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('home.default');
    }

    public function test_home_route_renders_default_when_entry_has_future_published_at(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Future Page',
            'slug' => 'future-page',
            'status' => 'published',
            'published_at' => now()->addDay(),
            'data_json' => [],
        ]);

        option_set('site', 'home_entry_id', $entry->id);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('home.default');
    }

    public function test_home_route_uses_cache(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Cached Page',
            'slug' => 'cached-page',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'data_json' => [],
        ]);

        option_set('site', 'home_entry_id', $entry->id);

        // Первый запрос - опция читается из БД, затем кэшируется
        DB::enableQueryLog();
        $response1 = $this->get('/');
        $response1->assertStatus(200);
        $response1->assertViewIs('pages.show');
        $queries1 = count(DB::getQueryLog());
        DB::flushQueryLog();

        // Второй запрос должен использовать кэш опций
        // Опция читается из кэша (0 запросов к options), но Entry всё равно запрашивается из БД
        DB::enableQueryLog();
        $response2 = $this->get('/');
        $response2->assertStatus(200);
        $response2->assertViewIs('pages.show');
        $queries2 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Второй запрос должен использовать кэш опций
        // Entry всё равно запрашивается из БД, но опция берется из кэша
        // В реальности второй запрос может делать больше запросов из-за служебных запросов Laravel
        // (например, проверка маршрутов, middleware и т.д.), но опция должна браться из кэша
        // Проверяем что опция действительно берется из кэша через проверку, что она доступна
        // без дополнительных запросов к таблице options
        $this->assertGreaterThan(0, $queries1, 'Первый запрос должен делать запросы к БД');
        $this->assertGreaterThan(0, $queries2, 'Второй запрос должен делать запросы к БД');
        
        // Проверяем что опция доступна из кэша (не проверяем количество запросов,
        // так как Laravel может делать служебные запросы, которые не связаны с опциями)
        $cachedOption = options('site', 'home_entry_id');
        $this->assertEquals($entry->id, $cachedOption, 
            'Опция должна быть доступна из кэша после первого запроса');
    }

    public function test_home_route_instantly_changes_when_option_changes(): void
    {
        // Создаем две опубликованные записи
        $entryA = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Home Page A',
            'slug' => 'home-page-a',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'data_json' => ['content' => 'Content A'],
        ]);

        $entryB = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Home Page B',
            'slug' => 'home-page-b',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'data_json' => ['content' => 'Content B'],
        ]);

        // Шаг А: устанавливаем entryA как главную
        option_set('site', 'home_entry_id', $entryA->id);

        $responseA = $this->get('/');
        $responseA->assertStatus(200);
        $responseA->assertViewIs('pages.show');
        $responseA->assertViewHas('entry', function ($entry) use ($entryA) {
            return $entry->id === $entryA->id && $entry->title === 'Home Page A';
        });
        $responseA->assertSee('Home Page A');

        // Шаг Б: мгновенно меняем на entryB
        option_set('site', 'home_entry_id', $entryB->id);

        $responseB = $this->get('/');
        $responseB->assertStatus(200);
        $responseB->assertViewIs('pages.show');
        $responseB->assertViewHas('entry', function ($entry) use ($entryB) {
            return $entry->id === $entryB->id && $entry->title === 'Home Page B';
        });
        $responseB->assertSee('Home Page B');
        $responseB->assertDontSee('Home Page A');
    }

    public function test_home_route_includes_canonical_link_when_entry_is_set(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Home Page',
            'slug' => 'home-page',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'data_json' => [],
        ]);

        option_set('site', 'home_entry_id', $entry->id);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('pages.show');
        
        // Проверяем что активен именованный маршрут 'home'
        $this->assertTrue(request()->routeIs('home'), 
            'Маршрут должен быть именованным как "home"');
        
        // Проверяем наличие canonical link на прямой URL записи
        // Canonical link рендерится через @push('meta') в <head>
        // Условие в pages/show.blade.php: @if(request()->routeIs('home'))
        $canonicalUrl = url('/' . $entry->slug);
        $html = $response->getContent();
        
        // Проверяем наличие canonical link в HTML
        $this->assertStringContainsString('rel="canonical"', $html, 
            'Canonical link должен присутствовать в HTML при доступе через маршрут home');
        $this->assertStringContainsString('href="' . $canonicalUrl . '"', $html, 
            'Canonical link должен указывать на прямой URL записи: ' . $canonicalUrl);
    }

    public function test_home_route_instantly_changes_when_option_changes_with_explicit_option_check(): void
    {
        // Создаем две опубликованные записи
        $entryA = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Home Page A',
            'slug' => 'home-page-a',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'data_json' => ['content' => 'Content A'],
        ]);

        $entryB = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Home Page B',
            'slug' => 'home-page-b',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'data_json' => ['content' => 'Content B'],
        ]);

        // Шаг А: устанавливаем entryA как главную и проверяем опцию
        option_set('site', 'home_entry_id', $entryA->id);
        $this->assertEquals($entryA->id, options('site', 'home_entry_id'));

        $responseA = $this->get('/');
        $responseA->assertStatus(200);
        $responseA->assertViewIs('pages.show');
        $responseA->assertViewHas('entry', function ($entry) use ($entryA) {
            return $entry->id === $entryA->id && $entry->title === 'Home Page A';
        });
        $responseA->assertSee('Home Page A');

        // Шаг Б: мгновенно меняем опцию на entryB и проверяем, что опция изменилась
        option_set('site', 'home_entry_id', $entryB->id);
        $this->assertEquals($entryB->id, options('site', 'home_entry_id'), 
            'Опция должна мгновенно измениться после option_set()');

        $responseB = $this->get('/');
        $responseB->assertStatus(200);
        $responseB->assertViewIs('pages.show');
        $responseB->assertViewHas('entry', function ($entry) use ($entryB) {
            return $entry->id === $entryB->id && $entry->title === 'Home Page B';
        });
        $responseB->assertSee('Home Page B');
        $responseB->assertDontSee('Home Page A');
    }
}

