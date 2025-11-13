<?php

namespace Tests\Unit;

use App\Domain\View\BladeTemplateResolver;
use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class BladeTemplateResolverTest extends TestCase
{
    use RefreshDatabase;

    private BladeTemplateResolver $resolver;
    private PostType $postType;
    private PostType $articleType;
    private string $viewsDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаём временную папку для views
        $this->viewsDir = base_path('tests/fixtures/views');
        File::ensureDirectoryExists($this->viewsDir);

        // Подменяем пути к views
        $originalPaths = config('view.paths', []);
        config(['view.paths' => array_merge([$this->viewsDir], $originalPaths)]);

        // Перезагружаем View finder
        app('view')->getFinder()->setPaths(config('view.paths'));
        View::flushFinderCache();

        $this->resolver = new BladeTemplateResolver(
            default: 'entry',
        );

        $this->postType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'template' => null,
            'options_json' => [],
        ]);

        $this->articleType = PostType::create([
            'slug' => 'article',
            'name' => 'Article',
            'template' => null,
            'options_json' => [],
        ]);
    }

    protected function tearDown(): void
    {
        // Очищаем временную папку
        if (File::exists($this->viewsDir)) {
            File::deleteDirectory($this->viewsDir);
        }

        // Восстанавливаем стандартные пути
        config(['view.paths' => [resource_path('views')]]);
        app('view')->getFinder()->setPaths(config('view.paths'));
        View::flushFinderCache();

        parent::tearDown();
    }

    /**
     * Создаёт файл шаблона в временной папке.
     * Поддерживает точки в имени (например, templates.custom → templates/custom.blade.php).
     */
    private function createViewFile(string $name, string $content = '@extends(\'layouts.app\')'): void
    {
        // Заменяем точки на слеши для создания подпапок
        $path = str_replace('.', '/', $name);
        $fullPath = "{$this->viewsDir}/{$path}.blade.php";
        
        // Создаём подпапки, если нужно
        $dir = dirname($fullPath);
        if ($dir !== $this->viewsDir) {
            File::ensureDirectoryExists($dir);
        }
        
        File::put($fullPath, $content);
        View::flushFinderCache();
    }

    /**
     * Удаляет файл шаблона из временной папки.
     */
    private function deleteViewFile(string $name): void
    {
        $path = "{$this->viewsDir}/{$name}.blade.php";
        if (File::exists($path)) {
            File::delete($path);
            View::flushFinderCache();
        }
    }

    public function test_returns_entry_when_only_default_exists(): void
    {
        // Создаём только entry.blade.php
        $this->createViewFile('entry');

        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('entry', $result);
    }

    public function test_returns_entry_post_type_template_when_exists(): void
    {
        // Создаём entry--article.blade.php
        $this->createViewFile('entry--article');

        $entry = Entry::create([
            'post_type_id' => $this->articleType->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('entry--article', $result);
    }

    public function test_returns_entry_post_type_slug_template_when_exists(): void
    {
        // Создаём entry--article--hello-world.blade.php
        $this->createViewFile('entry--article--hello-world');

        $entry = Entry::create([
            'post_type_id' => $this->articleType->id,
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('entry--article--hello-world', $result);
    }

    public function test_specific_slug_template_has_priority_over_post_type_template(): void
    {
        // Создаём оба шаблона
        $this->createViewFile('entry--article');
        $this->createViewFile('entry--article--hello-world');

        $entry = Entry::create([
            'post_type_id' => $this->articleType->id,
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        // Должен вернуться более специфичный шаблон
        $this->assertEquals('entry--article--hello-world', $result);
    }

    public function test_post_type_template_used_for_different_slug(): void
    {
        // Создаём entry--article.blade.php
        $this->createViewFile('entry--article');

        $entry = Entry::create([
            'post_type_id' => $this->articleType->id,
            'title' => 'Another Article',
            'slug' => 'another-article',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        // Должен использоваться entry--article, так как entry--article--another-article не существует
        $this->assertEquals('entry--article', $result);
    }

    public function test_returns_entry_template_override_when_set(): void
    {
        // Создаём шаблоны файловой конвенции
        $this->createViewFile('entry--article');
        $this->createViewFile('entry--article--hello-world');

        // Создаём override шаблон
        $this->createViewFile('templates.custom');

        $entry = Entry::create([
            'post_type_id' => $this->articleType->id,
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'status' => 'draft',
            'template_override' => 'templates.custom',
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        // Override должен побеждать всё
        $this->assertEquals('templates.custom', $result);
    }

    public function test_override_works_even_without_file_convention(): void
    {
        // Не создаём файлов конвенции, но задаём override
        $this->createViewFile('custom.template');

        $entry = Entry::create([
            'post_type_id' => $this->articleType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => 'custom.template',
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        // Override должен работать независимо от файловой конвенции
        $this->assertEquals('custom.template', $result);
    }

    public function test_override_throws_exception_when_template_not_exists(): void
    {
        // Не создаём override шаблон

        $entry = Entry::create([
            'post_type_id' => $this->articleType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => 'nonexistent.template',
            'data_json' => [],
        ]);
        $entry->load('postType');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Template override 'nonexistent.template' не найден. Убедитесь, что шаблон существует.");

        $this->resolver->forEntry($entry);
    }

    public function test_returns_default_when_no_templates_exist(): void
    {
        // Не создаём никаких шаблонов

        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('entry', $result);
    }

    public function test_returns_default_when_entry_override_empty_string(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => '',
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('entry', $result);
    }

    public function test_uses_custom_default_template(): void
    {
        $resolver = new BladeTemplateResolver(
            default: 'custom.default',
        );

        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $resolver->forEntry($entry);

        $this->assertEquals('custom.default', $result);
    }

    public function test_handles_missing_post_type_relation(): void
    {
        // Entry без загруженной связи postType
        // Должен запросить postType slug из БД через value('slug')
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('entry', $result);
        $this->assertFalse($entry->relationLoaded('postType'), 
            'Связь postType не должна быть загружена через lazy loading');
    }

    public function test_fallback_to_post_type_template_when_no_file_convention_exists(): void
    {
        // Не создаём файлов конвенции, но задаём post_types.template
        $this->articleType->update(['template' => 'pages.article']);

        // Создаём шаблон для back-compat
        $this->createViewFile('pages.article');

        Log::shouldReceive('channel')
            ->once()
            ->with('stack')
            ->andReturnSelf();
        
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'post_types.template устарел; используйте entry--{postType}. Переходный фолбэк: {template}',
                \Mockery::on(function ($context) {
                    return isset($context['postType']) && $context['postType'] === 'article' &&
                           isset($context['template']) && $context['template'] === 'pages.article';
                })
            );

        $entry = Entry::create([
            'post_type_id' => $this->articleType->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.article', $result);
    }

    public function test_file_convention_has_priority_over_post_type_template(): void
    {
        // Задаём post_types.template
        $this->articleType->update(['template' => 'pages.article']);

        // Создаём файл конвенции
        $this->createViewFile('entry--article');

        // Не должно быть warning, так как используется файловая конвенция
        Log::shouldReceive('channel')->never();
        Log::shouldReceive('warning')->never();

        $entry = Entry::create([
            'post_type_id' => $this->articleType->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        // Должна использоваться файловая конвенция, а не post_types.template
        $this->assertEquals('entry--article', $result);
    }

    public function test_handles_missing_post_type_relation_with_template_fallback(): void
    {
        // Задаём post_types.template
        $this->articleType->update(['template' => 'pages.article']);

        // Создаём шаблон для back-compat
        $this->createViewFile('pages.article');

        Log::shouldReceive('channel')
            ->once()
            ->with('stack')
            ->andReturnSelf();
        
        Log::shouldReceive('warning')
            ->once();

        $entry = Entry::create([
            'post_type_id' => $this->articleType->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);

        // Entry без загруженной связи postType
        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.article', $result);
        $this->assertFalse($entry->relationLoaded('postType'), 
            'Связь postType не должна быть загружена через lazy loading');
    }
}
