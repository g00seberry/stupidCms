<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Templates;

use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\FeatureTestCase;

class StoreTemplateTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        // Очищаем созданные тестовые шаблоны
        $testTemplates = [
            resource_path('views/test-template.blade.php'),
            resource_path('views/pages/test-article.blade.php'),
            resource_path('views/nested/test/template.blade.php'),
        ];

        foreach ($testTemplates as $template) {
            if (File::exists($template)) {
                File::delete($template);
            }
        }

        // Удаляем пустые директории
        $testDirs = [
            resource_path('views/nested/test'),
            resource_path('views/nested'),
        ];

        foreach ($testDirs as $dir) {
            if (File::isDirectory($dir) && count(File::files($dir)) === 0) {
                File::deleteDirectory($dir);
            }
        }

        parent::tearDown();
    }

    public function test_store_creates_template_successfully(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => 'test-template',
            'content' => '<div>Test Content</div>',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/templates', $payload, $admin);

        $response->assertStatus(201);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        $response->assertJsonPath('data.name', 'test-template');
        $response->assertJsonPath('data.path', 'test-template.blade.php');
        $response->assertJsonPath('data.exists', true);
        $response->assertJsonStructure([
            'data' => [
                'name',
                'path',
                'exists',
                'created_at',
            ],
        ]);

        // Проверяем, что файл создан
        $filePath = resource_path('views/test-template.blade.php');
        $this->assertFileExists($filePath);
        $this->assertEquals('<div>Test Content</div>', File::get($filePath));
    }

    public function test_store_creates_template_in_nested_directory(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => 'pages.test-article',
            'content' => '<article>Test Article</article>',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/templates', $payload, $admin);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'pages.test-article');
        $response->assertJsonPath('data.path', 'pages/test-article.blade.php');

        // Проверяем, что файл создан в правильной директории
        $filePath = resource_path('views/pages/test-article.blade.php');
        $this->assertFileExists($filePath);
        $this->assertEquals('<article>Test Article</article>', File::get($filePath));
    }

    public function test_store_creates_deeply_nested_template(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => 'nested.test.template',
            'content' => '<div>Deep Nested</div>',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/templates', $payload, $admin);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'nested.test.template');
        $response->assertJsonPath('data.path', 'nested/test/template.blade.php');

        // Проверяем, что файл создан
        $filePath = resource_path('views/nested/test/template.blade.php');
        $this->assertFileExists($filePath);
        $this->assertEquals('<div>Deep Nested</div>', File::get($filePath));
    }

    public function test_store_returns_409_when_template_already_exists(): void
    {
        $admin = User::factory()->admin()->create();

        // Создаём шаблон вручную
        $filePath = resource_path('views/test-template.blade.php');
        File::put($filePath, '<div>Existing</div>');

        $payload = [
            'name' => 'test-template',
            'content' => '<div>New Content</div>',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/templates', $payload, $admin);

        $response->assertStatus(409);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $this->assertErrorResponse($response, ErrorCode::CONFLICT, [
            'detail' => 'Template already exists.',
        ]);

        // Проверяем, что содержимое не изменилось
        $this->assertEquals('<div>Existing</div>', File::get($filePath));
    }

    public function test_store_requires_name_field(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->postJsonAsAdmin('/api/v1/admin/templates', [
            'content' => '<div>Content</div>',
        ], $admin);

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['name']);
    }

    public function test_store_requires_content_field(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->postJsonAsAdmin('/api/v1/admin/templates', [
            'name' => 'test-template',
        ], $admin);

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['content']);
    }

    public function test_store_validates_name_format(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->postJsonAsAdmin('/api/v1/admin/templates', [
            'name' => 'invalid name with spaces!',
            'content' => '<div>Content</div>',
        ], $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['name']);
    }

    public function test_store_validates_name_max_length(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->postJsonAsAdmin('/api/v1/admin/templates', [
            'name' => Str::random(256),
            'content' => '<div>Content</div>',
        ], $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['name']);
    }

    public function test_store_requires_authentication(): void
    {
        $csrfToken = Str::random(40);
        $csrfCookieName = config('security.csrf.cookie_name');

        $server = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-CSRF-Token' => $csrfToken,
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/admin/templates',
            [
                'name' => 'test-template',
                'content' => '<div>Content</div>',
            ],
            [$csrfCookieName => $csrfToken],
            [],
            $server,
            json_encode(['name' => 'test-template', 'content' => '<div>Content</div>'])
        );

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer');
        $this->assertErrorResponse($response, ErrorCode::UNAUTHORIZED, [
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }
}

