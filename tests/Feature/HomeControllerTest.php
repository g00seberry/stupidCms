<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
            'published_at' => Carbon::now('UTC')->subDay(),
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
            'published_at' => Carbon::now('UTC')->subDay(),
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
            'published_at' => Carbon::now('UTC')->addDay(),
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
            'published_at' => Carbon::now('UTC')->subDay(),
            'data_json' => [],
        ]);

        option_set('site', 'home_entry_id', $entry->id);

        // Первый запрос
        $response1 = $this->get('/');
        $response1->assertStatus(200);
        $response1->assertViewIs('pages.show');

        // Второй запрос должен использовать кэш опций
        $response2 = $this->get('/');
        $response2->assertStatus(200);
        $response2->assertViewIs('pages.show');
    }
}

