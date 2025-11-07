<?php

namespace Tests\Feature;

use App\Models\RouteReservation;
use App\Models\User;
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
        $response = $this->actingAs($this->admin, 'admin')->postJson('/api/v1/admin/reservations', [
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);

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
        RouteReservation::create([
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->postJson('/api/v1/admin/reservations', [
            'path' => '/feed.xml',
            'source' => 'plugin:shop',
        ]);

        $response->assertStatus(409);
        $response->assertJsonStructure([
            'type',
            'title',
            'status',
            'detail',
            'path',
            'owner',
        ]);
        $response->assertJson([
            'status' => 409,
            'title' => 'Conflict',
            'path' => '/feed.xml',
            'owner' => 'system:feeds',
        ]);
    }

    public function test_store_invalid_path_returns_422(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->postJson('/api/v1/admin/reservations', [
            'path' => '',
            'source' => 'system:feeds',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/admin/reservations', [
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);

        $response->assertUnauthorized();
    }

    public function test_store_requires_admin_permissions(): void
    {
        $response = $this->actingAs($this->regularUser, 'admin')->postJson('/api/v1/admin/reservations', [
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);

        $response->assertForbidden();
    }

    public function test_destroy_releases_reservation(): void
    {
        RouteReservation::create([
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->deleteJson('/api/v1/admin/reservations/feed.xml', [
            'source' => 'system:feeds',
        ]);

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
        RouteReservation::create([
            'path' => '/blog/rss',
            'source' => 'system:feeds',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->deleteJson('/api/v1/admin/reservations/blog/rss', [
            'source' => 'system:feeds',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('reserved_routes', [
            'path' => '/blog/rss',
        ]);
    }

    public function test_destroy_wrong_source_returns_403(): void
    {
        RouteReservation::create([
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->deleteJson('/api/v1/admin/reservations/feed.xml', [
            'source' => 'plugin:other',
        ]);

        $response->assertStatus(403);
        $response->assertJsonStructure([
            'type',
            'title',
            'status',
            'detail',
            'path',
            'owner',
            'attempted_source',
        ]);
        $response->assertJson([
            'status' => 403,
            'title' => 'Forbidden',
        ]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/v1/admin/reservations/feed.xml', [
            'source' => 'system:feeds',
        ]);

        $response->assertUnauthorized();
    }

    public function test_destroy_requires_admin_permissions(): void
    {
        $response = $this->actingAs($this->regularUser, 'admin')->deleteJson('/api/v1/admin/reservations/feed.xml', [
            'source' => 'system:feeds',
        ]);

        $response->assertForbidden();
    }

    public function test_index_lists_reservations(): void
    {
        RouteReservation::create([
            'path' => '/feed.xml',
            'source' => 'system:feeds',
        ]);
        RouteReservation::create([
            'path' => '/sitemap.xml',
            'source' => 'system:sitemap',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->getJson('/api/v1/admin/reservations');

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
        $response = $this->actingAs($this->regularUser, 'admin')->getJson('/api/v1/admin/reservations');

        $response->assertForbidden();
    }
}

