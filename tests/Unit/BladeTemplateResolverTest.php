<?php

namespace Tests\Unit;

use App\Domain\View\BladeTemplateResolver;
use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class BladeTemplateResolverTest extends TestCase
{
    use RefreshDatabase;

    private BladeTemplateResolver $resolver;
    private PostType $postType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new BladeTemplateResolver(
            default: 'pages.show',
            overridePrefix: 'pages.overrides.',
            typePrefix: 'pages.types.',
        );

        $this->postType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);
    }

    public function test_returns_override_when_exists(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'about',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // Мокаем View::exists для override
        View::shouldReceive('exists')
            ->once()
            ->with('pages.overrides.about')
            ->andReturn(true);

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.overrides.about', $result);
    }

    public function test_returns_type_template_when_override_not_exists(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'data_json' => [],
        ]);
        $entry->load('postType');

        // Override не существует
        View::shouldReceive('exists')
            ->once()
            ->with('pages.overrides.test')
            ->andReturn(false);

        // Type template существует
        View::shouldReceive('exists')
            ->once()
            ->with('pages.types.page')
            ->andReturn(true);

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.types.page', $result);
    }

    public function test_returns_default_when_override_and_type_not_exist(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'data_json' => [],
        ]);
        $entry->load('postType');

        // Override не существует
        View::shouldReceive('exists')
            ->once()
            ->with('pages.overrides.test')
            ->andReturn(false);

        // Type template не существует
        View::shouldReceive('exists')
            ->once()
            ->with('pages.types.page')
            ->andReturn(false);

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.show', $result);
    }

    public function test_override_has_highest_priority(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'about',
            'status' => 'draft',
            'data_json' => [],
        ]);
        $entry->load('postType');

        // Override существует - должен вернуть его, НЕ проверяя type и default
        // Если бы проверял type/default, View::exists был бы вызван несколько раз
        View::shouldReceive('exists')
            ->once()  // Только один раз для override
            ->with('pages.overrides.about')
            ->andReturn(true);
        // НЕ должно быть вызовов для pages.types.page или проверки default

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.overrides.about', $result);
    }

    public function test_type_template_has_priority_over_default(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'data_json' => [],
        ]);
        $entry->load('postType');

        // Override не существует
        View::shouldReceive('exists')
            ->once()
            ->with('pages.overrides.test')
            ->andReturn(false);

        // Type template существует - должен вернуть его, НЕ используя default
        // Если бы использовал default, не было бы вызова для type
        View::shouldReceive('exists')
            ->once()
            ->with('pages.types.page')
            ->andReturn(true);
        // НЕ должно быть возврата default без проверки type

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.types.page', $result);
        $this->assertNotEquals('pages.show', $result, 'Type template должен иметь приоритет над default');
    }

    public function test_sanitizes_slug_for_security(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'about<script>alert(1)',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // EntryObserver нормализует slug через Slugifier при создании,
        // поэтому 'about<script>alert(1)' становится 'about-script-alert-1'
        // Затем sanitizeSlug() удаляет недопустимые символы, оставляя дефисы
        // Ожидаемый санитизированный slug: 'about-script-alert-1'
        $expectedSanitizedSlug = 'about-script-alert-1';
        $expectedView = 'pages.overrides.' . $expectedSanitizedSlug;

        View::shouldReceive('exists')
            ->once()
            ->with($expectedView)
            ->andReturn(true);

        $result = $this->resolver->forEntry($entry);

        // Проверяем что результат санитизирован и соответствует ожидаемому
        $this->assertEquals($expectedView, $result);
        $this->assertStringStartsWith('pages.overrides.', $result);
        $this->assertStringNotContainsString('<', $result, 'Санитизация должна удалять <');
        $this->assertStringNotContainsString('>', $result, 'Санитизация должна удалять >');
        $this->assertStringNotContainsString('(', $result, 'Санитизация должна удалять (');
        $this->assertStringNotContainsString(')', $result, 'Санитизация должна удалять )');
        $this->assertStringContainsString($expectedSanitizedSlug, $result, 'Должен содержать санитизированный slug');
    }

    public function test_uses_memoization_for_view_exists(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'data_json' => [],
        ]);
        $entry->load('postType');

        // View::exists должен быть вызван только один раз для каждого view
        // При первом вызове forEntry()
        View::shouldReceive('exists')
            ->once()  // Только один раз для override
            ->with('pages.overrides.test')
            ->andReturn(false);

        View::shouldReceive('exists')
            ->once()  // Только один раз для type
            ->with('pages.types.page')
            ->andReturn(false);

        // Первый вызов - View::exists вызывается
        $result1 = $this->resolver->forEntry($entry);
        $this->assertEquals('pages.show', $result1);

        // Второй вызов - должен использовать кэш из $existsCache
        // View::exists НЕ должен вызываться снова (моки уже проверены)
        $result2 = $this->resolver->forEntry($entry);
        $this->assertEquals('pages.show', $result2);
        
        // Если бы мемоизация не работала, моки выбросили бы ошибку
        // о неожиданных вызовах View::exists
    }

    public function test_priority_order_override_type_default(): void
    {
        // Комплексный тест приоритетов: override > type > default
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test-page',
            'status' => 'draft',
            'data_json' => [],
        ]);
        $entry->load('postType');

        // Сценарий 1: все три уровня существуют - должен вернуть override
        View::shouldReceive('exists')
            ->once()
            ->with('pages.overrides.test-page')
            ->andReturn(true);

        $result1 = $this->resolver->forEntry($entry);
        $this->assertEquals('pages.overrides.test-page', $result1, 
            'Override должен иметь наивысший приоритет');

        // Создаем новый resolver для следующего теста (кэш сброшен)
        $resolver2 = new BladeTemplateResolver(
            default: 'pages.show',
            overridePrefix: 'pages.overrides.',
            typePrefix: 'pages.types.',
        );

        // Сценарий 2: override не существует, type существует - должен вернуть type
        View::shouldReceive('exists')
            ->once()
            ->with('pages.overrides.test-page')
            ->andReturn(false);

        View::shouldReceive('exists')
            ->once()
            ->with('pages.types.page')
            ->andReturn(true);

        $result2 = $resolver2->forEntry($entry);
        $this->assertEquals('pages.types.page', $result2, 
            'Type должен иметь приоритет над default');

        // Создаем новый resolver для следующего теста
        $resolver3 = new BladeTemplateResolver(
            default: 'pages.show',
            overridePrefix: 'pages.overrides.',
            typePrefix: 'pages.types.',
        );

        // Сценарий 3: override и type не существуют - должен вернуть default
        View::shouldReceive('exists')
            ->once()
            ->with('pages.overrides.test-page')
            ->andReturn(false);

        View::shouldReceive('exists')
            ->once()
            ->with('pages.types.page')
            ->andReturn(false);

        $result3 = $resolver3->forEntry($entry);
        $this->assertEquals('pages.show', $result3, 
            'Default должен использоваться когда override и type отсутствуют');
    }

    public function test_handles_missing_post_type_relation(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // Entry без загруженной связи postType
        // Должен запросить slug из БД через value('slug'), а не через lazy loading
        // Это предотвращает N+1 запросы
        View::shouldReceive('exists')
            ->once()
            ->with('pages.overrides.test')
            ->andReturn(false);

        View::shouldReceive('exists')
            ->once()
            ->with('pages.types.page')
            ->andReturn(false);

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.show', $result);
        
        // Проверяем, что связь postType не была загружена (lazy loading не произошел)
        // Если бы произошел lazy loading, $entry->postType был бы доступен
        $this->assertFalse($entry->relationLoaded('postType'), 
            'Связь postType не должна быть загружена через lazy loading');
    }

    public function test_handles_empty_slug_after_sanitization(): void
    {
        // Создаем Entry с slug, который после санитизации станет пустым
        // Например, slug состоит только из недопустимых символов
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => '!!!@@@###', // Только знаки препинания, которые будут удалены
            'status' => 'draft',
            'data_json' => [],
        ]);
        $entry->load('postType');

        // После санитизации slug станет пустым, поэтому проверка override должна быть пропущена
        // (не должно быть вызова View::exists для override с пустым slug)
        // Должен сразу перейти к проверке type template
        
        // НЕ должно быть вызова View::exists для override (так как slug пустой)
        // Должен быть вызов только для type template
        View::shouldReceive('exists')
            ->once()
            ->with('pages.types.page')
            ->andReturn(true);

        $result = $this->resolver->forEntry($entry);

        // Должен вернуть type template, так как override был пропущен из-за пустого slug
        $this->assertEquals('pages.types.page', $result);
    }

    public function test_handles_empty_slug_after_sanitization_falls_back_to_default(): void
    {
        // Аналогично предыдущему тесту, но type template не существует
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => '!!!@@@###', // Только знаки препинания
            'status' => 'draft',
            'data_json' => [],
        ]);
        $entry->load('postType');

        // НЕ должно быть вызова View::exists для override (пустой slug)
        // Должен быть вызов для type template
        View::shouldReceive('exists')
            ->once()
            ->with('pages.types.page')
            ->andReturn(false);

        $result = $this->resolver->forEntry($entry);

        // Должен вернуть default, так как override пропущен, а type не существует
        $this->assertEquals('pages.show', $result);
    }

    public function test_override_wins_when_both_override_and_type_exist(): void
    {
        // Smoke-тест: проверяем, что при наличии и override, и type шаблона
        // выигрывает override (приоритет выше)
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'special-page',
            'status' => 'draft',
            'data_json' => [],
        ]);
        $entry->load('postType');

        // Мокаем, что оба шаблона существуют
        // Override должен быть проверен первым и вернуться сразу
        View::shouldReceive('exists')
            ->once()
            ->with('pages.overrides.special-page')
            ->andReturn(true);

        // НЕ должно быть вызова для type template, так как override уже найден
        // Если бы был вызов для 'pages.types.page', тест упал бы из-за mock

        $result = $this->resolver->forEntry($entry);

        // Должен вернуть override, игнорируя type template
        $this->assertEquals('pages.overrides.special-page', $result);
    }
}

