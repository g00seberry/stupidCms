<?php

declare(strict_types=1);

use App\Enums\RouteNodeActionType;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\RouteNode;
use App\Repositories\RouteNodeRepository;
use App\Services\DynamicRoutes\DynamicRouteCache;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use App\Services\DynamicRoutes\DynamicRouteRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->cache = new DynamicRouteCache();
    $this->repository = new RouteNodeRepository($this->cache);
    $this->guard = new DynamicRouteGuard();
    $this->registrar = new DynamicRouteRegistrar($this->repository, $this->guard);
    
    // Очищаем роуты перед каждым тестом
    Route::getRoutes()->refreshNameLookups();
    Route::getRoutes()->refreshActionLookups();
});

test('Роут action_type=ENTRY с entry_id возвращает опубликованную Entry', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->published()->create([
        'post_type_id' => $postType->id,
        'title' => 'About Us',
    ]);

    $node = RouteNode::factory()->route()->withEntry($entry)->create([
        'methods' => ['GET'],
        'uri' => 'about',
    ]);

    $this->registrar->register();

    $response = $this->getJson('/about');

    $response->assertOk()
        ->assertJsonStructure([
            'entry' => ['id', 'title', 'status', 'published_at', 'data_json'],
            'post_type' => ['id', 'name'],
            'blueprint',
            'route' => ['id', 'uri'],
        ])
        ->assertJsonPath('entry.id', $entry->id)
        ->assertJsonPath('entry.title', 'About Us')
        ->assertJsonPath('entry.status', 'published');
});

test('Если entry_id отсутствует → 404', function () {
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'no-entry',
        'action_type' => RouteNodeActionType::ENTRY,
        'entry_id' => null,
    ]);

    $this->registrar->register();

    $response = $this->getJson('/no-entry');

    $response->assertNotFound();
});

test('Если Entry status=draft → 404 (публичный доступ)', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'status' => Entry::STATUS_DRAFT,
    ]);

    $node = RouteNode::factory()->route()->withEntry($entry)->create([
        'methods' => ['GET'],
        'uri' => 'draft-page',
    ]);

    $this->registrar->register();

    $response = $this->getJson('/draft-page');

    $response->assertNotFound();
});

test('Если published_at > now() → 404', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'status' => Entry::STATUS_PUBLISHED,
        'published_at' => now()->addDay(),
    ]);

    $node = RouteNode::factory()->route()->withEntry($entry)->create([
        'methods' => ['GET'],
        'uri' => 'scheduled-page',
    ]);

    $this->registrar->register();

    $response = $this->getJson('/scheduled-page');

    $response->assertNotFound();
});

test('JSON-ответ содержит entry, post_type, blueprint, route', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->published()->create([
        'post_type_id' => $postType->id,
        'title' => 'Test Page',
    ]);

    $node = RouteNode::factory()->route()->withEntry($entry)->create([
        'methods' => ['GET'],
        'uri' => 'test-page',
        'name' => 'test.page',
    ]);

    $this->registrar->register();

    $response = $this->getJson('/test-page');

    $response->assertOk()
        ->assertJsonStructure([
            'entry',
            'post_type',
            'blueprint',
            'route',
        ])
        ->assertJsonPath('route.uri', 'test-page')
        ->assertJsonPath('route.name', 'test.page');
});

test('Узел enabled=false → маршрут недоступен', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->published()->create([
        'post_type_id' => $postType->id,
    ]);

    $node = RouteNode::factory()->route()->disabled()->withEntry($entry)->create([
        'methods' => ['GET'],
        'uri' => 'disabled-entry',
    ]);

    $this->registrar->register();

    $response = $this->getJson('/disabled-entry');

    $response->assertNotFound();
});

test('Опция require_published=false позволяет показывать draft (для preview)', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'status' => Entry::STATUS_DRAFT,
    ]);

    $node = RouteNode::factory()->route()->withEntry($entry)->create([
        'methods' => ['GET'],
        'uri' => 'preview-page',
        'options' => ['require_published' => false],
    ]);

    $this->registrar->register();

    $response = $this->getJson('/preview-page');

    $response->assertOk()
        ->assertJsonPath('entry.status', 'draft');
});

