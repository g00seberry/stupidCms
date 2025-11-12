<?php

namespace Tests\Feature\Admin\Entries;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryStatusesTest extends TestCase
{
    use RefreshDatabase;

    public function test_statuses_returns_200_with_list_of_statuses(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries/statuses', $admin);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        $response->assertJson([
            'data' => [
                'draft',
                'published',
            ],
        ]);
        $response->assertJsonCount(2, 'data');
    }

    public function test_statuses_returns_correct_status_values(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries/statuses', $admin);

        $response->assertOk();
        $statuses = $response->json('data');
        
        $this->assertContains('draft', $statuses);
        $this->assertContains('published', $statuses);
        $this->assertEquals(Entry::getStatuses(), $statuses);
    }

    public function test_statuses_returns_401_when_not_authenticated(): void
    {
        $response = $this->getJson('/api/v1/admin/entries/statuses');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer');
    }

    public function test_statuses_returns_403_for_non_admin_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries/statuses', $user);

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_statuses_allows_user_with_manage_entries_permission(): void
    {
        $editor = User::factory()->create(['is_admin' => false]);
        $editor->grantAdminPermissions('manage.entries');
        $editor->save();

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries/statuses', $editor);

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'draft',
                'published',
            ],
        ]);
    }
}

