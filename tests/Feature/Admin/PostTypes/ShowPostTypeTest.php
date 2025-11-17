<?php

namespace Tests\Feature\Admin\PostTypes;

use App\Models\PostType;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Tests\Support\FeatureTestCase;

class ShowPostTypeTest extends FeatureTestCase
{
    public function test_show_post_type_returns_200_with_correct_structure(): void
    {
        $admin = $this->admin();
        
        $postType = PostType::factory()->create([
            'slug' => 'page',
            'name' => 'Страница',
            'options_json' => [
                'template' => 'default',
                'editor' => ['toolbar' => ['h2', 'bold', 'link']],
            ],
        ]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/post-types/page', $admin);

        $response->assertStatus(200);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        
        $response->assertJson([
            'data' => [
                'slug' => 'page',
                'name' => 'Страница',
                'options_json' => [
                    'template' => 'default',
                    'editor' => ['toolbar' => ['h2', 'bold', 'link']],
                    'taxonomies' => [],
                ],
            ],
        ]);

        $response->assertJsonStructure([
            'data' => [
                'slug',
                'name',
                'options_json',
                'updated_at',
            ],
        ]);
    }

    public function test_show_post_type_with_empty_options_returns_empty_object(): void
    {
        $admin = $this->admin();
        
        $postType = PostType::factory()->create([
            'slug' => 'article',
            'options_json' => [],
        ]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/post-types/article', $admin);

        $response->assertStatus(200);
        $decoded = json_decode($response->getContent());
        $this->assertInstanceOf(\stdClass::class, $decoded->data->options_json);
        $this->assertEquals('article', $decoded->data->slug);
        $this->assertSame(['taxonomies' => []], (array) $decoded->data->options_json);
    }

    public function test_show_post_type_returns_404_for_unknown_slug(): void
    {
        $admin = $this->admin();

        $response = $this->getJsonAsAdmin('/api/v1/admin/post-types/nonexistent', $admin);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        
        $this->assertErrorResponse($response, ErrorCode::NOT_FOUND, [
            'detail' => 'Unknown post type slug: nonexistent',
            'meta.slug' => 'nonexistent',
        ]);
    }

    public function test_show_post_type_returns_401_when_not_authenticated(): void
    {
        PostType::factory()->create(['slug' => 'page']);

        $response = $this->getJson('/api/v1/admin/post-types/page');

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

    public function test_show_post_type_returns_403_for_non_admin_user(): void
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
            '/api/v1/admin/post-types/page',
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

    public function test_show_post_type_allows_user_with_manage_posttypes_permission(): void
    {
        $editor = $this->admin(['manage.posttypes']);

        PostType::factory()->create([
            'slug' => 'page',
            'options_json' => ['key' => 'value'],
        ]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/post-types/page', $editor);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        $response->assertJson([
            'data' => [
                'slug' => 'page',
            ],
        ]);
    }
}

