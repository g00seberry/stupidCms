<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\PostTypes;

use App\Models\PostType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexPostTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_all_post_types_with_expected_shape(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PostType::factory()->create([
            'slug' => 'article',
            'name' => 'Article',
            'options_json' => [
                'template' => 'default',
            ],
        ]);

        PostType::factory()->create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/post-types', $admin);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-cache, private');
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'slug' => 'article',
            'name' => 'Article',
            'options_json' => [
                'template' => 'default',
                'taxonomies' => [],
            ],
        ]);

        $decoded = json_decode($response->getContent());
        $this->assertInstanceOf(\stdClass::class, $decoded->data[1]->options_json);
        $this->assertSame(['taxonomies' => []], (array) $decoded->data[1]->options_json);
    }

    public function test_index_returns_401_when_not_authenticated(): void
    {
        $response = $this->getJson('/api/v1/admin/post-types');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer');
        $response->assertHeader('Cache-Control', 'no-store, private');

        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_index_returns_403_for_user_without_manage_permission(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        PostType::factory()->create(['slug' => 'page']);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
        ]);

        $this->assertFalse($user->can('manage.posttypes'));

        $response = $this->getJsonWithUnencryptedCookie(
            '/api/v1/admin/post-types',
            config('jwt.cookies.access'),
            $token
        );

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');

        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'This action is unauthorized.',
        ]);
    }

    public function test_index_allows_user_with_manage_posttypes_permission(): void
    {
        $editor = User::factory()->create([
            'is_admin' => false,
            'admin_permissions' => ['manage.posttypes'],
        ]);

        PostType::factory()->create([
            'slug' => 'page',
            'options_json' => ['key' => 'value'],
        ]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/post-types', $editor);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-cache, private');
        $response->assertJsonFragment([
            'slug' => 'page',
        ]);
    }
}


