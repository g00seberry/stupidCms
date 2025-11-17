<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Support\MediaTestCase;
use Illuminate\Support\Facades\Gate;

final class MediaIndexTest extends MediaTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        // Разрешаем все проверки политик для упрощения feature-тестов выборки
        Gate::before(function ($user = null, string $ability = '') {
            return true;
        });
        // Аутентифицируем администратора (FormRequest->authorize() требует user())
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
    }

    public function test_lists_media_with_default_filters_via_repository(): void
    {
        Media::factory()->count(2)->image()->create(['title' => 'Hero one', 'collection' => 'banners']);
        Media::factory()->document()->create(['original_name' => 'file.pdf', 'collection' => 'documents']);

        $this->withoutMiddleware();

        $response = $this->getJson('/api/v1/admin/media');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->has('meta')
                ->etc()
            );
    }

    public function test_filters_by_kind_document(): void
    {
        Media::factory()->count(2)->image()->create();
        $doc = Media::factory()->document()->create(['original_name' => 'spec.pdf']);

        $this->withoutMiddleware();

        $response = $this->getJson('/api/v1/admin/media?kind=document');
        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data', 1)
                ->has('data.0', fn (AssertableJson $j) => $j
                    ->where('name', $doc->original_name)
                    ->etc()
                )
                ->etc()
            );
    }

    public function test_applies_search_by_q(): void
    {
        Media::factory()->image()->create(['title' => 'Summer Hero']);
        $target = Media::factory()->image()->create(['original_name' => 'winter-hero.jpg']);

        $this->withoutMiddleware();
        $response = $this->getJson('/api/v1/admin/media?q=winter-hero');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data', 1)
                ->has('data.0', fn (AssertableJson $j) => $j
                    ->where('name', $target->original_name)
                    ->etc()
                )
                ->etc()
            );
    }

    public function test_handles_soft_delete_filters(): void
    {
        Media::factory()->image()->create();
        $trashed = Media::factory()->image()->create();
        $trashed->delete();

        $this->withoutMiddleware();

        $this->getJson('/api/v1/admin/media')->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json->has('data', 1)->etc());

        $this->getJson('/api/v1/admin/media?deleted=with')->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json->has('data', 2)->etc());

        $this->getJson('/api/v1/admin/media?deleted=only')->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json->has('data', 1)->etc());
    }

    public function test_sorts_and_orders_results(): void
    {
        $older = Media::factory()->image()->create(['size_bytes' => 1000, 'created_at' => now()->subDay()]);
        Media::factory()->image()->create(['size_bytes' => 2000, 'created_at' => now()]);

        $this->withoutMiddleware();

        $this->getJson('/api/v1/admin/media?sort=size_bytes&order=asc')->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.0', fn (AssertableJson $j) => $j
                    ->where('size_bytes', (int) $older->size_bytes)
                    ->etc()
                )
                ->etc()
            );
    }
}


