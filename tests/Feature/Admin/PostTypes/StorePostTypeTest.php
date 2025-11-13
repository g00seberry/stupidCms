<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\PostTypes;

use App\Models\PostType;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorePostTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_post_type_with_options(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $payload = [
            'slug' => 'product',
            'name' => 'Product',
            'options_json' => [
                'fields' => [
                    'price' => ['type' => 'number'],
                ],
                'taxonomies' => ['catalog'],
            ],
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/post-types', $payload, $admin);

        $response->assertStatus(201);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        $response->assertJsonPath('data.slug', 'product');
        $response->assertJsonPath('data.name', 'Product');
        $response->assertJsonPath('data.options_json.fields.price.type', 'number');

        $this->assertDatabaseHas('post_types', [
            'slug' => 'product',
            'name' => 'Product',
            'options_json' => json_encode($payload['options_json']),
        ]);
    }

    public function test_store_requires_unique_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PostType::factory()->create(['slug' => 'product']);

        $response = $this->postJsonAsAdmin('/api/v1/admin/post-types', [
            'slug' => 'product',
            'name' => 'Duplicate',
        ], $admin);

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR, [
            'meta.errors.slug.0' => 'The slug has already been taken.',
        ]);
    }

    public function test_store_rejects_list_options_payload(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->postJsonAsAdmin('/api/v1/admin/post-types', [
            'slug' => 'gallery',
            'name' => 'Gallery',
            'options_json' => ['one', 'two'],
        ], $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR, [
            'meta.errors.options_json.0' => 'The options_json field must be an object.',
        ]);
    }

    public function test_store_returns_401_when_not_authenticated(): void
    {
        $payload = [
            'slug' => 'product',
            'name' => 'Product',
        ];

        $csrfToken = Str::random(40);
        $csrfCookieName = config('security.csrf.cookie_name');

        $server = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-CSRF-Token' => $csrfToken,
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/admin/post-types',
            $payload,
            [$csrfCookieName => $csrfToken],
            [],
            $server,
            json_encode($payload)
        );

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $this->assertErrorResponse($response, ErrorCode::UNAUTHORIZED, [
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_store_requires_manage_posttypes_permission(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->postJsonAsAdmin('/api/v1/admin/post-types', [
            'slug' => 'gallery',
            'name' => 'Gallery',
        ], $user);

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        $this->assertErrorResponse($response, ErrorCode::FORBIDDEN, [
            'detail' => 'This action is unauthorized.',
        ]);
    }

    public function test_store_allows_user_with_manage_posttypes_permission(): void
    {
        $editor = User::factory()->create([
            'is_admin' => false,
            'admin_permissions' => ['manage.posttypes'],
        ]);

        $response = $this->postJsonAsAdmin('/api/v1/admin/post-types', [
            'slug' => 'gallery',
            'name' => 'Gallery',
        ], $editor);

        $response->assertStatus(201);
        $response->assertJsonPath('data.slug', 'gallery');
        $this->assertDatabaseHas('post_types', ['slug' => 'gallery']);
    }
}


