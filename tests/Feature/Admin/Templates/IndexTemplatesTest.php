<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Templates;

use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class IndexTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_200_with_templates_structure(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates', $admin);

        $response->assertOk();
        // Проверяем наличие заголовка Cache-Control (может быть no-cache или no-store)
        $response->assertHeader('Cache-Control');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        // Vary заголовок может отсутствовать, если нет Set-Cookie
        if ($response->headers->has('Vary')) {
            $this->assertStringContainsString('Cookie', $response->headers->get('Vary'));
        }
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'name',
                    'path',
                    'exists',
                ],
            ],
        ]);
    }

    public function test_index_returns_sorted_list(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates', $admin);

        $response->assertOk();
        $templates = $response->json('data');

        // Проверяем, что список отсортирован по name
        $names = array_column($templates, 'name');
        $sorted = $names;
        sort($sorted);
        $this->assertEquals($sorted, $names, 'Список шаблонов должен быть отсортирован');
    }

    public function test_index_excludes_system_directories(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates', $admin);

        $response->assertOk();
        $templates = $response->json('data');

        // Проверяем, что системные директории исключены
        $systemPrefixes = ['admin.', 'errors.', 'layouts.', 'partials.', 'vendor.'];

        foreach ($templates as $template) {
            foreach ($systemPrefixes as $prefix) {
                $this->assertFalse(
                    str_starts_with($template['name'], $prefix),
                    "Шаблон '{$template['name']}' не должен начинаться с системного префикса '{$prefix}'"
                );
            }
        }
    }

    public function test_index_includes_existing_templates(): void
    {
        $admin = User::factory()->admin()->create();
        $viewsPath = resource_path('views');

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates', $admin);

        $response->assertOk();
        $templates = $response->json('data');
        $templateNames = array_column($templates, 'name');

        // Проверяем наличие базовых шаблонов, если они существуют
        $expectedTemplates = [];

        if (File::exists($viewsPath . '/pages/show.blade.php')) {
            $expectedTemplates[] = 'pages.show';
        }

        if (File::exists($viewsPath . '/home/default.blade.php')) {
            $expectedTemplates[] = 'home.default';
        }

        if (File::exists($viewsPath . '/welcome.blade.php')) {
            $expectedTemplates[] = 'welcome';
        }

        if (!empty($expectedTemplates)) {
            foreach ($expectedTemplates as $expected) {
                $this->assertContains(
                    $expected,
                    $templateNames,
                    "Шаблон '{$expected}' должен присутствовать в списке"
                );
            }
        }
    }

    public function test_index_returns_correct_path_for_templates(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates', $admin);

        $response->assertOk();
        $templates = $response->json('data');

        foreach ($templates as $template) {
            // Проверяем, что path соответствует name
            $expectedPath = str_replace('.', '/', $template['name']) . '.blade.php';
            $this->assertEquals(
                $expectedPath,
                $template['path'],
                "Path для шаблона '{$template['name']}' должен быть '{$expectedPath}'"
            );
        }
    }

    public function test_index_returns_correct_exists_flag(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates', $admin);

        $response->assertOk();
        $templates = $response->json('data');

        foreach ($templates as $template) {
            $filePath = resource_path('views/' . $template['path']);
            $expectedExists = File::exists($filePath);
            $this->assertEquals(
                $expectedExists,
                $template['exists'],
                "Флаг exists для шаблона '{$template['name']}' должен быть {$expectedExists}"
            );
        }
    }

    public function test_index_handles_nested_directories(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/templates', $admin);

        $response->assertOk();
        $templates = $response->json('data');

        foreach ($templates as $template) {
            // Проверяем формат: должен содержать только допустимые символы
            $isValid = preg_match('/^[a-z0-9._-]+$/i', $template['name']) === 1;
            $this->assertTrue(
                $isValid,
                "Шаблон '{$template['name']}' должен быть в корректном dot notation формате"
            );
        }
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/templates');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::UNAUTHORIZED, [
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }
}

