<?php

namespace Tests\Feature\Admin\PostTypes;

use App\Models\PostType;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatePostTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_post_type_returns_200_and_updates_options(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $postType = PostType::factory()->create([
            'slug' => 'page',
            'name' => 'Страница',
            'options_json' => [
                'template' => 'default',
            ],
        ]);

        $newOptions = [
            'template' => 'landing',
            'seo' => ['noindex' => false],
        ];

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/page', [
            'options_json' => $newOptions,
        ], $admin);

        $response->assertStatus(200);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        
        $response->assertJson([
            'data' => [
                'slug' => 'page',
                'name' => 'Страница',
                'options_json' => $newOptions,
            ],
        ]);

        // Verify database was updated
        $this->assertDatabaseHas('post_types', [
            'slug' => 'page',
            'options_json' => json_encode($newOptions),
        ]);
    }

    public function test_update_post_type_with_nested_options_is_valid(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $postType = PostType::factory()->create([
            'slug' => 'article',
            'options_json' => ['key' => 'value'],
        ]);

        $newOptions = [
            'nested' => [
                'deep' => [
                    'value' => 'test',
                ],
            ],
        ];

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/article', [
            'options_json' => $newOptions,
        ], $admin);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('article', $data['slug']);
        $this->assertEquals($newOptions, $data['options_json']);
    }

    public function test_update_post_type_only_changes_options_json(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $postType = PostType::factory()->create([
            'slug' => 'page',
            'name' => 'Original Name',
            'options_json' => ['old' => 'value'],
        ]);

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/page', [
            'options_json' => ['new' => 'value'],
        ], $admin);

        $response->assertStatus(200);

        $postType->refresh();
        
        // Verify only options_json was changed
        $this->assertEquals('page', $postType->slug);
        $this->assertEquals('Original Name', $postType->name);
        $this->assertEquals(['new' => 'value'], $postType->options_json);
    }

    public function test_update_post_type_returns_404_for_unknown_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/nonexistent', ['options_json' => ['foo' => 'bar']], $admin);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        
        $this->assertErrorResponse($response, ErrorCode::NOT_FOUND, [
            'detail' => 'Unknown post type slug: nonexistent',
            'meta.slug' => 'nonexistent',
        ]);
    }

    public function test_update_post_type_returns_422_when_options_json_is_missing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        PostType::factory()->create(['slug' => 'page']);

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/page', [], $admin);

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR, [
            'detail' => 'The options_json field is required.',
        ]);
        $this->assertValidationErrors($response, ['options_json' => 'The options_json field is required.']);

        $response->assertJsonPath('errors.options_json', ['The options_json field is required.']);
    }

    public function test_update_post_type_returns_422_when_options_json_is_not_object(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        PostType::factory()->create(['slug' => 'page']);

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/page', [
            'options_json' => 'not an object',
        ], $admin);

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR, [
            'detail' => 'The options_json field must be an object.',
        ]);
        $this->assertValidationErrors($response, ['options_json' => 'The options_json field must be an object.']);

        $response->assertJsonPath('errors.options_json', ['The options_json field must be an object.']);
    }

    public function test_update_post_type_rejects_list_payload(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/page', [
            'options_json' => ['one', 'two'],
        ], $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR, [
            'detail' => 'The options_json field must be an object.',
        ]);
        $this->assertValidationErrors($response, ['options_json' => 'The options_json field must be an object.']);
    }

    public function test_update_post_type_returns_empty_object_when_payload_is_empty_array(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/page', [
            'options_json' => [],
        ], $admin);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $decoded = json_decode($response->getContent());
        $this->assertInstanceOf(\stdClass::class, $decoded->data->options_json);
        $this->assertSame([], (array) $decoded->data->options_json);
    }

    public function test_update_post_type_allows_user_with_manage_posttypes_permission(): void
    {
        $editor = User::factory()->create([
            'is_admin' => false,
            'admin_permissions' => ['manage.posttypes'],
        ]);

        PostType::factory()->create([
            'slug' => 'page',
            'options_json' => ['foo' => 'bar'],
        ]);

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/page', [
            'options_json' => ['foo' => 'baz'],
        ], $editor);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        $response->assertJsonPath('data.options_json.foo', 'baz');
    }

    public function test_update_post_type_returns_401_when_not_authenticated(): void
    {
        PostType::factory()->create(['slug' => 'page']);

        // Use withoutMiddleware for CSRF to test auth only, or add CSRF token
        $csrfToken = \Illuminate\Support\Str::random(40);
        $csrfCookieName = config('security.csrf.cookie_name');
        
        $server = $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-CSRF-Token' => $csrfToken,
        ]);

        $response = $this->call('PUT', '/api/v1/admin/post-types/page', [
            'options_json' => ['key' => 'value'],
        ], [$csrfCookieName => $csrfToken], [], $server, json_encode(['options_json' => ['key' => 'value']]));

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

    public function test_update_post_type_returns_403_for_non_admin_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        PostType::factory()->create(['slug' => 'page']);

        $this->assertFalse($user->can('manage.posttypes'));

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/page', [
            'options_json' => ['key' => 'value'],
        ], $user);

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

    public function test_update_post_type_updates_name_and_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $postType = PostType::factory()->create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => ['old' => 'value'],
        ]);

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/page', [
            'slug' => 'page',
            'name' => 'Pageыы',
            'options_json' => ['new' => 'value'],
        ], $admin);

        $response->assertStatus(200);
        
        $postType->refresh();
        
        $this->assertEquals('page', $postType->slug);
        $this->assertEquals('Pageыы', $postType->name);
        $this->assertEquals(['new' => 'value'], $postType->options_json);
        
        $response->assertJson([
            'data' => [
                'slug' => 'page',
                'name' => 'Pageыы',
                'options_json' => ['new' => 'value'],
            ],
        ]);
    }

    public function test_update_post_type_can_change_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $postType = PostType::factory()->create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);

        $response = $this->putJsonAsAdmin('/api/v1/admin/post-types/page', [
            'slug' => 'article',
            'name' => 'Article',
            'options_json' => [],
        ], $admin);

        $response->assertStatus(200);
        
        $postType->refresh();
        
        $this->assertEquals('article', $postType->slug);
        $this->assertEquals('Article', $postType->name);
        
        $response->assertJson([
            'data' => [
                'slug' => 'article',
                'name' => 'Article',
            ],
        ]);
    }


}

