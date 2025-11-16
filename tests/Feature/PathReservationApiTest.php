<?php

namespace Tests\Feature;

use App\Models\ReservedRoute;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PathReservationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->regularUser = User::factory()->create();
    }

    public function test_store_creates_reservation(): void
    {
        $response = $this->postJsonAsAdmin('/api/v1/admin/reservations', [
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ], $this->admin);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Path reserved successfully',
        ]);

        $this->assertDatabaseHas('reserved_routes', [
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);
    }

    public function test_store_duplicate_returns_409(): void
    {
        ReservedRoute::create([
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);

        $response = $this->postJsonAsAdmin('/api/v1/admin/reservations', [
            'path' => '/feed.xml',
            'source' => 'plugin:shop',
        ], $this->admin);

        $response->assertStatus(409);
        $this->assertErrorResponse($response, ErrorCode::CONFLICT, [
            'status' => 409,
            'meta.path' => '/feed.xml',
            'meta.owner' => 'system:feeds',
        ]);
    }

    public function test_store_invalid_path_returns_422(): void
    {
        $response = $this->postJsonAsAdmin('/api/v1/admin/reservations', [
            'path' => '',
            'source' => 'system:feeds',
        ], $this->admin);

        $response->assertStatus(422);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJsonWithCsrf('/api/v1/admin/reservations', [
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);

        $response->assertUnauthorized();
    }

    public function test_store_requires_admin_permissions(): void
    {
        $response = $this->postJsonAsAdmin('/api/v1/admin/reservations', [
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ], $this->regularUser);

        $response->assertForbidden();
    }

    public function test_destroy_releases_reservation(): void
    {
        ReservedRoute::create([
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/reservations/feed.xml', [
            'source' => 'system:feeds',
        ], $this->admin);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Path released successfully',
        ]);

        $this->assertDatabaseMissing('reserved_routes', [
            'path' => '/feed.xml',
        ]);
    }

    public function test_destroy_with_multi_segment_path(): void
    {
        ReservedRoute::create([
            'path' => '/blog/rss',
            'source' => 'system:feeds',
        ]);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/reservations/blog/rss', [
            'source' => 'system:feeds',
        ], $this->admin);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('reserved_routes', [
            'path' => '/blog/rss',
        ]);
    }

    public function test_destroy_wrong_source_returns_403(): void
    {
        ReservedRoute::create([
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/reservations/feed.xml', [
            'source' => 'plugin:other',
        ], $this->admin);

        $response->assertStatus(403);
        $this->assertErrorResponse($response, ErrorCode::FORBIDDEN, [
            'status' => 403,
            'meta.path' => '/feed.xml',
            'meta.owner' => 'system:feeds',
            'meta.attempted_source' => 'plugin:other',
        ]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $response = $this->deleteJsonWithCsrf('/api/v1/admin/reservations/feed.xml', [
            'source' => 'system:feeds',
        ]);

        $response->assertUnauthorized();
    }

    public function test_destroy_requires_admin_permissions(): void
    {
        $response = $this->deleteJsonAsAdmin('/api/v1/admin/reservations/feed.xml', [
            'source' => 'system:feeds',
        ], $this->regularUser);

        $response->assertForbidden();
    }

    public function test_index_lists_reservations(): void
    {
        ReservedRoute::create([
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);
        ReservedRoute::create([
            'path' => '/sitemap.xml',
            'source' => 'system:sitemap',
        ]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/reservations', $this->admin);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['path', 'kind', 'source', 'created_at'],
            ],
        ]);
        $response->assertJsonCount(2, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/reservations');

        $response->assertUnauthorized();
    }

    public function test_index_requires_admin_permissions(): void
    {
        $response = $this->getJsonAsAdmin('/api/v1/admin/reservations', $this->regularUser);

        $response->assertForbidden();
    }
}

