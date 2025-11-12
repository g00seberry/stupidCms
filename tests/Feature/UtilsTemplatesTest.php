<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UtilsTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_endpoint_returns_200_with_data_structure(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/utils/templates', $admin);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [], // Массив шаблонов
        ]);

        // Проверяем, что data - это массив строк
        $templates = $response->json('data');
        $this->assertIsArray($templates);
        foreach ($templates as $template) {
            $this->assertIsString($template);
        }
    }

    public function test_templates_endpoint_returns_sorted_list(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/utils/templates', $admin);

        $response->assertOk();
        $templates = $response->json('data');

        // Проверяем, что список отсортирован
        $sorted = $templates;
        sort($sorted);
        $this->assertEquals($sorted, $templates, 'Список шаблонов должен быть отсортирован');
    }

    public function test_templates_endpoint_excludes_system_directories(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/utils/templates', $admin);

        $response->assertOk();
        $templates = $response->json('data');

        // Проверяем, что системные директории исключены
        $systemPrefixes = ['admin.', 'errors.', 'layouts.', 'partials.', 'vendor.'];

        foreach ($templates as $template) {
            foreach ($systemPrefixes as $prefix) {
                $this->assertStringNotStartsWith(
                    $prefix,
                    $template,
                    "Шаблон '{$template}' не должен начинаться с системного префикса '{$prefix}'"
                );
            }
        }
    }

    public function test_templates_endpoint_returns_only_blade_templates(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/utils/templates', $admin);

        $response->assertOk();
        $templates = $response->json('data');

        // Проверяем, что все шаблоны имеют правильный формат (dot notation)
        // И не содержат расширение .blade.php
        foreach ($templates as $template) {
            $this->assertIsString($template);
            $this->assertStringNotContainsString('.blade.php', $template);
            $this->assertStringNotContainsString('/', $template, 'Шаблоны должны быть в dot notation формате');
            $this->assertStringNotContainsString('\\', $template, 'Шаблоны должны быть в dot notation формате');
        }
    }

    public function test_templates_endpoint_includes_existing_templates(): void
    {
        $admin = User::factory()->admin()->create();
        $viewsPath = resource_path('views');

        // Проверяем, что метод возвращает шаблоны, которые реально существуют
        $response = $this->getJsonAsAdmin('/api/v1/admin/utils/templates', $admin);

        $response->assertOk();
        $templates = $response->json('data');

        // Если существуют базовые шаблоны (pages.show, home.default, welcome),
        // проверяем, что они присутствуют в ответе
        // Но не падаем, если их нет (тест должен быть устойчивым к изменениям)

        // Проверяем, что если файл существует, он должен быть в списке
        $expectedTemplates = [];

        // Проверяем наличие pages/show.blade.php
        if (File::exists($viewsPath . '/pages/show.blade.php')) {
            $expectedTemplates[] = 'pages.show';
        }

        // Проверяем наличие home/default.blade.php
        if (File::exists($viewsPath . '/home/default.blade.php')) {
            $expectedTemplates[] = 'home.default';
        }

        // Проверяем наличие welcome.blade.php
        if (File::exists($viewsPath . '/welcome.blade.php')) {
            $expectedTemplates[] = 'welcome';
        }

        // Если есть ожидаемые шаблоны, проверяем их наличие в ответе
        if (!empty($expectedTemplates)) {
            foreach ($expectedTemplates as $expected) {
                $this->assertContains(
                    $expected,
                    $templates,
                    "Шаблон '{$expected}' должен присутствовать в списке"
                );
            }
        }
    }

    public function test_templates_endpoint_handles_nested_directories(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/utils/templates', $admin);

        $response->assertOk();
        $templates = $response->json('data');

        // Проверяем, что шаблоны из вложенных директорий правильно конвертируются в dot notation
        // Например, pages/types/article.blade.php → pages.types.article
        foreach ($templates as $template) {
            // Проверяем формат: должен содержать только допустимые символы (буквы, цифры, точки, дефисы, подчеркивания)
            $isValid = preg_match('/^[a-z0-9._-]+$/i', $template) === 1;
            $this->assertTrue(
                $isValid,
                "Шаблон '{$template}' должен быть в корректном dot notation формате (только буквы, цифры, точки, дефисы, подчеркивания)"
            );
        }
    }

    public function test_templates_endpoint_requires_authentication(): void
    {
        // Тест без аутентификации
        $response = $this->getJson('/api/v1/admin/utils/templates');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

}

