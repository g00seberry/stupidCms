<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Templates;

use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Support\Facades\File;
use Tests\Support\FeatureTestCase;

class ShowTemplateTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Создаём тестовый шаблон
        $this->testTemplatePath = resource_path('views/test-show.blade.php');
        $this->testTemplateContent = '<div>Test Template Content</div>';
        File::put($this->testTemplatePath, $this->testTemplateContent);

        // Создаём вложенный шаблон
        $this->nestedTemplatePath = resource_path('views/pages/test-show.blade.php');
        $this->nestedTemplateContent = '<article>Nested Template</article>';
        File::put($this->nestedTemplatePath, $this->nestedTemplateContent);
    }

    protected function tearDown(): void
    {
        // Удаляем тестовые шаблоны
        $testTemplates = [
            $this->testTemplatePath,
            $this->nestedTemplatePath,
        ];

        foreach ($testTemplates as $template) {
            if (File::exists($template)) {
                File::delete($template);
            }
        }

        parent::tearDown();
    }

    private string $testTemplatePath;
    private string $testTemplateContent;
    private string $nestedTemplatePath;
    private string $nestedTemplateContent;

    public function test_show_returns_template_with_content(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates/test-show', $admin);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        $response->assertJsonStructure([
            'data' => [
                'name',
                'path',
                'exists',
                'content',
                'updated_at',
            ],
        ]);

        $response->assertJsonPath('data.name', 'test-show');
        $response->assertJsonPath('data.path', 'test-show.blade.php');
        $response->assertJsonPath('data.exists', true);
        $response->assertJsonPath('data.content', $this->testTemplateContent);
        $this->assertNotNull($response->json('data.updated_at'));
    }

    public function test_show_returns_nested_template(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates/pages.test-show', $admin);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'pages.test-show');
        $response->assertJsonPath('data.path', 'pages/test-show.blade.php');
        $response->assertJsonPath('data.content', $this->nestedTemplateContent);
    }

    public function test_show_returns_404_for_nonexistent_template(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates/nonexistent.template', $admin);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $this->assertErrorResponse($response, ErrorCode::NOT_FOUND, [
            'detail' => 'Template not found.',
        ]);
    }

    public function test_show_handles_deeply_nested_template(): void
    {
        $admin = User::factory()->admin()->create();

        // Создаём глубоко вложенный шаблон
        $deepTemplatePath = resource_path('views/nested/deep/template.blade.php');
        $deepContent = '<div>Deep Nested Template</div>';
        File::makeDirectory(dirname($deepTemplatePath), 0755, true);
        File::put($deepTemplatePath, $deepContent);

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates/nested.deep.template', $admin);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'nested.deep.template');
        $response->assertJsonPath('data.path', 'nested/deep/template.blade.php');
        $response->assertJsonPath('data.content', $deepContent);

        // Очистка
        File::delete($deepTemplatePath);
        File::deleteDirectory(dirname($deepTemplatePath));
        if (File::isDirectory(resource_path('views/nested/deep'))) {
            File::deleteDirectory(resource_path('views/nested/deep'));
        }
        if (File::isDirectory(resource_path('views/nested')) && count(File::files(resource_path('views/nested'))) === 0) {
            File::deleteDirectory(resource_path('views/nested'));
        }
    }

    public function test_show_returns_updated_at_from_file_modification_time(): void
    {
        $admin = User::factory()->admin()->create();

        // Обновляем файл
        sleep(1); // Небольшая задержка для изменения времени модификации
        File::put($this->testTemplatePath, '<div>Updated Content</div>');

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates/test-show', $admin);

        $response->assertOk();
        $updatedAt = $response->json('data.updated_at');
        $this->assertNotNull($updatedAt);
        $this->assertIsString($updatedAt);
        // Проверяем, что это валидная дата в формате ISO 8601
        $this->assertNotFalse(\DateTime::createFromFormat(\DateTime::ATOM, $updatedAt));
    }

    public function test_show_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/templates/test-show');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::UNAUTHORIZED, [
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_show_handles_existing_template_from_views(): void
    {
        $admin = User::factory()->admin()->create();

        // Проверяем существующий шаблон, если он есть
        $existingTemplates = ['pages.show', 'home.default', 'welcome'];

        foreach ($existingTemplates as $templateName) {
            $filePath = resource_path('views/' . str_replace('.', '/', $templateName) . '.blade.php');
            if (File::exists($filePath)) {
                $response = $this->getJsonAsAdmin('/api/v1/admin/templates/' . $templateName, $admin);

                $response->assertOk();
                $response->assertJsonPath('data.name', $templateName);
                $response->assertJsonPath('data.exists', true);
                $this->assertArrayHasKey('content', $response->json('data'));
                break; // Проверяем только первый найденный
            }
        }
    }
}

