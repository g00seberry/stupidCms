# Code Review: Задача 35 - Санитайзер richtext

## Обзор изменений

Реализована задача 35 (Санитайзер richtext) с интеграцией HTMLPurifier для автоматической санитизации richtext полей при сохранении Entry.

**Изменено файлов:** 3
**Создано новых файлов:** 3

**Статус:** ✅ Реализовано, все тесты проходят

---

## 1. config/purifier.php

**Статус:** НОВЫЙ ФАЙЛ

```php
<?php

return [
    'settings' => [
        'cms_default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'Cache.SerializerPath' => storage_path('app/purifier'),

            // Теги/атрибуты
            // Примечание: figure и figcaption добавляются через кастомную конфигурацию HTMLDefinition
            'HTML.AllowedElements' => [
                'a','abbr','b','blockquote','br','code','em','i','hr','img','li','ol','p','pre','s','small','strong','sub','sup','u','ul','h1','h2','h3','h4','h5','h6','div','span','figure','figcaption'
            ],
            'HTML.AllowedAttributes' => [
                'a.href','a.title','a.target','a.rel',
                'img.src','img.alt','img.title','img.width','img.height',
            ],
            'URI.AllowedSchemes' => [ 'http' => true, 'https' => true, 'mailto' => true ],

            // Удаляем скрипты/ивенты
            'HTML.SafeScripting' => [],
            'HTML.SafeEmbed' => false,
            'HTML.SafeObject' => false,
            'Attr.EnableID' => false,
            // onload, onclick и т.д. блокируются через белый список атрибутов (HTML.AllowedAttributes)
            // HTMLPurifier не поддерживает Attr.ForbiddenPatterns в стандартной конфигурации

            // Автоформатирование
            'AutoFormat.RemoveEmpty' => true,
            'AutoFormat.Linkify' => false, // ссылкование оставим на редактор
            'AutoFormat.AutoParagraph' => false,

            // Изображения
            'URI.DisableExternalResources' => false,
            'CSS.AllowedProperties' => [], // стили запрещаем в базе профиля
        ],
        // Кастомные определения для HTML5 элементов
        'custom_definition' => [
            'id' => 'cms_default_html5',
            'rev' => 1,
            'debug' => false,
            'elements' => [
                // HTML5 семантические элементы
                ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
                ['figcaption', 'Inline', 'Flow', 'Common'],
            ],
        ],
    ],
];
```

**Описание:**

-   Конфигурационный файл для HTMLPurifier
-   Профиль `cms_default` с белым списком разрешенных тегов и атрибутов
-   Используется `HTML 4.01 Transitional` (HTMLPurifier не поддерживает HTML5 doctype напрямую)
-   HTML5 элементы `figure` и `figcaption` добавляются через `custom_definition`
-   Запрещены скрипты, инлайновые обработчики и небезопасные схемы URI
-   Включен кэш для производительности
-   Внешние изображения разрешены (`URI.DisableExternalResources` => false) — допустимы `<img>` с `https` схемами

---

## 2. app/Domain/Sanitizer/RichTextSanitizer.php

**Статус:** НОВЫЙ ФАЙЛ

```php
<?php

namespace App\Domain\Sanitizer;

use Mews\Purifier\Facades\Purifier;

final class RichTextSanitizer
{
    public function __construct(private string $profile = 'cms_default') {}

    public function sanitize(string $html): string
    {
        // Сохраняем href ссылок с target="_blank" до санитизации для сопоставления
        $targetBlankHrefs = [];
        if (preg_match_all('#<a\b[^>]*\btarget\s*=\s*([\'"]?)_blank\1[^>]*>#i', $html, $matches)) {
            foreach ($matches[0] as $tag) {
                if (preg_match('#href\s*=\s*([\'"])(.*?)\1#i', $tag, $hrefMatch)) {
                    $targetBlankHrefs[] = $hrefMatch[2];
                }
            }
        }

        $clean = Purifier::clean($html, $this->profile);

        // Обрабатываем каждую ссылку индивидуально
        $clean = preg_replace_callback(
            '#<a\b[^>]*>#i',
            function (array $m) use ($targetBlankHrefs) {
                $tag = $m[0];

                // Проверяем, была ли эта ссылка с target="_blank" в исходном HTML
                $wasTargetBlank = false;
                if (preg_match('#href\s*=\s*([\'"])(.*?)\1#i', $tag, $hrefMatch)) {
                    $href = $hrefMatch[2];
                    $wasTargetBlank = in_array($href, $targetBlankHrefs, true);
                }

                // Также проверяем, есть ли target="_blank" в очищенной ссылке
                // (HTMLPurifier может его сохранить, если он в белом списке)
                $hasTargetBlank = preg_match('#\btarget\s*=\s*([\'"]?)_blank\1#i', $tag);

                // Обрабатываем только если была target="_blank" в исходном HTML
                // или есть в очищенной ссылке
                if (!$wasTargetBlank && !$hasTargetBlank) {
                    return $tag;
                }

                // Извлекаем существующий rel (если есть)
                if (preg_match('#\brel\s*=\s*([\'"])(.*?)\1#i', $tag, $rm)) {
                    $quote = $rm[1];
                    $tokens = preg_split('/\s+/', trim($rm[2])) ?: [];
                    $need = array_diff(['noopener', 'noreferrer'], array_map('strtolower', $tokens));

                    if ($need) {
                        $new = implode(' ', array_unique(array_merge($tokens, $need)));
                        // Заменяем значение rel в исходном теге
                        $tag = preg_replace('#\brel\s*=\s*[\'"].*?[\'"]#i', 'rel=' . $quote . $new . $quote, $tag, 1);
                    }

                    return $tag;
                }

                // rel нет — добавляем
                return rtrim(substr($tag, 0, -1)) . ' rel="noopener noreferrer">';
            },
            $clean
        );

        return $clean;
    }
}
```

**Описание:**

-   Сервис для санитизации HTML контента
-   Использует HTMLPurifier с профилем `cms_default`
-   HTMLPurifier автоматически удаляет небезопасные атрибуты и схемы
-   Пост-обработка: автоматически добавляет `rel="noopener noreferrer"` для ссылок с `target="_blank"`
-   Проверяет каждую ссылку индивидуально по исходному HTML (до санитизации)
-   Сохраняет `href` ссылок с `target="_blank"` до санитизации для сопоставления
-   После санитизации сопоставляет ссылки по `href` и добавляет `rel`
-   Если `rel` уже существует, добавляет недостающие токены (`noopener`, `noreferrer`), сохраняя существующие значения
-   Ссылки без `target="_blank"` не модифицируются
-   Final класс для предотвращения наследования

---

## 3. app/Observers/EntryObserver.php

**Статус:** ИЗМЕНЕН

**Изменения:**

1. Добавлен импорт `RichTextSanitizer`
2. Добавлен `RichTextSanitizer` в конструктор
3. Добавлен вызов `sanitizeRichTextFields()` в `creating()`
4. Добавлен вызов `sanitizeRichTextFields()` в `updating()` при изменении `data_json`
5. Добавлен метод `sanitizeRichTextFields()`

**Полный код:**

```php
<?php

namespace App\Observers;

use App\Domain\Sanitizer\RichTextSanitizer;
use App\Models\Entry;
use App\Models\ReservedRoute;
use App\Support\EntrySlug\EntrySlugService;
use App\Support\Slug\Slugifier;
use App\Support\Slug\SlugOptions;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EntryObserver
{
    /**
     * Временное хранилище для старых slug'ов (по ID записи)
     * Используется для передачи старого slug из updating() в updated()
     */
    private static array $oldSlugs = [];

    public function __construct(
        private Slugifier $slugifier,
        private UniqueSlugService $uniqueSlugService,
        private EntrySlugService $entrySlugService,
        private RichTextSanitizer $sanitizer,
    ) {}

    public function creating(Entry $entry): void
    {
        $this->ensureSlug($entry);
        $this->sanitizeRichTextFields($entry);
    }

    public function updating(Entry $entry): void
    {
        // Если изменился title или slug, пересчитываем
        if ($entry->isDirty(['title', 'slug'])) {
            // Сохраняем оригинальный slug ДО изменения (для истории)
            // Важно: читаем getOriginal() до вызова ensureSlug(), так как ensureSlug может изменить slug
            $oldSlug = $entry->getOriginal('slug');
            $this->ensureSlug($entry);
            // Сохраняем старый slug во временном хранилище для использования в updated()
            if ($entry->exists) {
                self::$oldSlugs[$entry->id] = $oldSlug;
            }
        }

        // Санитизируем richtext поля при изменении data_json
        if ($entry->isDirty('data_json')) {
            $this->sanitizeRichTextFields($entry);
        }
    }

    public function created(Entry $entry): void
    {
        $this->entrySlugService->onCreated($entry);
    }

    public function updated(Entry $entry): void
    {
        if ($entry->wasChanged('slug')) {
            // Используем сохраненный оригинальный slug из временного хранилища
            $oldSlug = self::$oldSlugs[$entry->id] ?? $entry->getOriginal('slug');
            $this->entrySlugService->onUpdated($entry, $oldSlug);
            // Очищаем временное хранилище
            unset(self::$oldSlugs[$entry->id]);
        }
    }

    private function ensureSlug(Entry $entry): void
    {
        // Если пользователь задал кастомный slug — прогоняем через мягкий slugify
        if (!empty($entry->slug)) {
            $opts = new SlugOptions(toLower: true, asciiOnly: true);
            $entry->slug = $this->slugifier->slugify($entry->slug, $opts);
        } elseif (!empty($entry->title)) {
            // Если slug пуст — генерируем из title с явными опциями
            $opts = new SlugOptions(toLower: true, asciiOnly: true);
            $entry->slug = $this->slugifier->slugify($entry->title, $opts);
        }

        if (empty($entry->slug)) {
            return;
        }

        // Получаем post_type_id для скоупа
        $postTypeId = $entry->post_type_id ?? $entry->postType?->id;

        // Загружаем зарезервированные пути в память (кэш для производительности)
        [$prefixes, $paths] = \Illuminate\Support\Facades\Cache::remember(
            'reserved_routes_ci',
            300,
            function () {
                return [
                    ReservedRoute::where('kind', 'prefix')
                        ->pluck('path')
                        ->map(fn($p) => mb_strtolower($p, 'UTF-8'))
                        ->all(),
                    ReservedRoute::where('kind', 'path')
                        ->pluck('path')
                        ->map(fn($p) => mb_strtolower($p, 'UTF-8'))
                        ->all(),
                ];
            }
        );

        // Проверяем занятость: в скоупе типа записи + зарезервированные пути
        $entry->slug = $this->uniqueSlugService->ensureUnique(
            $entry->slug,
            function (string $slug) use ($entry, $postTypeId, $prefixes, $paths) {
                // Проверка уникальности в скоупе post_type_id
                $exists = Entry::query()
                    ->where('slug', $slug)
                    ->where('post_type_id', $postTypeId)
                    ->when($entry->exists, fn($q) => $q->where('id', '!=', $entry->id))
                    ->exists();

                // Проверка зарезервированных путей в памяти (быстрее, чем SQL)
                $slugLower = Str::lower($slug);
                $reserved = in_array($slugLower, $paths, true)
                    || in_array($slugLower, $prefixes, true)
                    || collect($prefixes)->contains(fn($prefix) => Str::startsWith($slugLower, $prefix . '/'));

                return $exists || $reserved;
            }
        );
    }

    /**
     * Санитизирует richtext поля (body_html, excerpt_html) из data_json
     * и сохраняет очищенный HTML в body_html_sanitized/excerpt_html_sanitized
     */
    private function sanitizeRichTextFields(Entry $entry): void
    {
        $data = $entry->data_json ?? [];

        // Санитизируем body_html
        if (isset($data['body_html']) && is_string($data['body_html'])) {
            $data['body_html_sanitized'] = $this->sanitizer->sanitize($data['body_html']);
        }

        // Санитизируем excerpt_html
        if (isset($data['excerpt_html']) && is_string($data['excerpt_html'])) {
            $data['excerpt_html_sanitized'] = $this->sanitizer->sanitize($data['excerpt_html']);
        }

        $entry->data_json = $data;
    }
}
```

**Описание изменений:**

-   Добавлена автоматическая санитизация richtext полей при создании и обновлении Entry
-   Санитизация применяется только при изменении `data_json` (оптимизация производительности)
-   Очищенный HTML сохраняется в `body_html_sanitized` и `excerpt_html_sanitized`
-   Оригинальные поля `body_html` и `excerpt_html` сохраняются без изменений

---

## 4. app/Providers/AppServiceProvider.php

**Статус:** ИЗМЕНЕН

**Изменения:**

1. Добавлен импорт `RichTextSanitizer`
2. Добавлена регистрация `RichTextSanitizer` как singleton

**Полный код:**

```php
<?php

namespace App\Providers;

use App\Domain\Options\OptionsRepository;
use App\Domain\Sanitizer\RichTextSanitizer;
use App\Domain\View\BladeTemplateResolver;
use App\Domain\View\TemplateResolver;
use App\Models\Entry;
use App\Observers\EntryObserver;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрация OptionsRepository
        $this->app->singleton(OptionsRepository::class, function ($app) {
            return new OptionsRepository($app->make(CacheRepository::class));
        });

        // Регистрация TemplateResolver
        // Используем scoped вместо singleton для совместимости с Octane/Swoole
        // Это гарантирует, что мемоизация View::exists() не протекает между запросами
        $this->app->scoped(TemplateResolver::class, function () {
            return new BladeTemplateResolver(
                default: config('view_templates.default', 'pages.show'),
                overridePrefix: config('view_templates.override_prefix', 'pages.overrides.'),
                typePrefix: config('view_templates.type_prefix', 'pages.types.'),
            );
        });

        // Регистрация RichTextSanitizer
        $this->app->singleton(RichTextSanitizer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Entry::observe(EntryObserver::class);
    }
}
```

**Описание изменений:**

-   Добавлена регистрация `RichTextSanitizer` как singleton для переиспользования экземпляра

---

## 5. app/Providers/AppServiceProvider.php

**Статус:** ИЗМЕНЕН

**Изменения:**

1. Добавлено создание директории для кэша HTMLPurifier в методе `boot()`

**Полный код метода boot():**

```php
public function boot(): void
{
    Entry::observe(EntryObserver::class);

    // Создаем директорию для кэша HTMLPurifier (idempotent)
    app('files')->ensureDirectoryExists(storage_path('app/purifier'));
}
```

**Описание изменений:**

-   Добавлено автоматическое создание директории для кэша HTMLPurifier при загрузке приложения
-   Операция идемпотентна (безопасна при повторных вызовах)

---

## 6. tests/Unit/RichTextSanitizerTest.php

**Статус:** НОВЫЙ ФАЙЛ

```php
<?php

namespace Tests\Unit;

use App\Domain\Sanitizer\RichTextSanitizer;
use Tests\TestCase;

class RichTextSanitizerTest extends TestCase
{
    private RichTextSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = app(RichTextSanitizer::class);
    }

    public function test_script_removed_and_anchor_kept(): void
    {
        $html = '<p>Hello</p><script>alert(1)</script><a href="https://example.com">x</a>';
        $clean = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringContainsString('<a href="https://example.com"', $clean);
    }

    public function test_target_blank_gets_rel_noopener(): void
    {
        $html = '<a href="https://ex.com" target="_blank">ex</a>';
        $clean = $this->sanitizer->sanitize($html);
        // После пост-обработки должен появиться rel="noopener noreferrer"
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertStringContainsString('<a href="https://ex.com"', $clean);
    }

    public function test_target_blank_appends_noopener_when_rel_exists(): void
    {
        $html = '<a href="https://ex.com" target="_blank" rel="nofollow">ex</a>';
        $clean = $this->sanitizer->sanitize($html);
        // Если уже есть rel, добавляем недостающие noopener и noreferrer
        // HTMLPurifier может удалить rel="nofollow", но если rel сохранился, добавляем noopener
        $this->assertStringContainsString('<a href="https://ex.com"', $clean);
        // Проверяем, что если rel есть, то он содержит noopener и noreferrer
        if (preg_match('#rel\s*=\s*"([^"]*)"#i', $clean, $matches)) {
            $relTokens = array_map('strtolower', preg_split('/\s+/', trim($matches[1])));
            $this->assertContains('noopener', $relTokens);
            $this->assertContains('noreferrer', $relTokens);
        } else {
            // Если rel удален HTMLPurifier, наш код добавит noopener
            $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        }
    }

    public function test_rel_not_added_when_no_target_blank(): void
    {
        // Ссылки без target="_blank" не должны получать rel="noopener noreferrer"
        $html = '<a href="https://ex.com">Link 1</a><a href="https://other.com" target="_blank">Link 2</a>';
        $clean = $this->sanitizer->sanitize($html);

        // Первая ссылка не должна иметь rel="noopener noreferrer"
        $this->assertStringContainsString('<a href="https://ex.com"', $clean);
        // Проверяем, что первая ссылка не имеет rel="noopener noreferrer"
        if (preg_match('#<a\b[^>]*href\s*=\s*"https://ex\.com"[^>]*>#i', $clean, $match)) {
            $this->assertStringNotContainsString('rel="noopener noreferrer"', $match[0]);
        }

        // Вторая ссылка должна иметь rel="noopener noreferrer"
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_onclick_attribute_removed(): void
    {
        $html = '<p onclick="alert(1)">Hello</p>';
        $clean = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('onclick', $clean);
    }

    public function test_allowed_tags_preserved(): void
    {
        $html = '<h1>Title</h1><p>Paragraph with <strong>bold</strong> and <em>italic</em> text.</p><ul><li>Item 1</li><li>Item 2</li></ul>';
        $clean = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<h1>', $clean);
        $this->assertStringContainsString('<p>', $clean);
        $this->assertStringContainsString('<strong>', $clean);
        $this->assertStringContainsString('<em>', $clean);
        $this->assertStringContainsString('<ul>', $clean);
        $this->assertStringContainsString('<li>', $clean);
    }

    public function test_javascript_scheme_removed(): void
    {
        $html = '<a href="javascript:alert(1)">Click</a>';
        $clean = $this->sanitizer->sanitize($html);
        // JavaScript схемы должны быть удалены или заменены
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_http_https_schemes_allowed(): void
    {
        $html = '<a href="https://example.com">HTTPS</a><a href="http://example.com">HTTP</a>';
        $clean = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('href="http://example.com"', $clean);
    }

    public function test_mailto_scheme_allowed(): void
    {
        $html = '<a href="mailto:test@example.com">Email</a>';
        $clean = $this->sanitizer->sanitize($html);
        $this->assertStringContainsString('href="mailto:test@example.com"', $clean);
    }

    public function test_img_tag_with_allowed_attributes(): void
    {
        $html = '<img src="https://example.com/image.jpg" alt="Image" title="Title" width="100" height="100">';
        $clean = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<img', $clean);
        $this->assertStringContainsString('src="https://example.com/image.jpg"', $clean);
        $this->assertStringContainsString('alt="Image"', $clean);
    }

    public function test_empty_string_returns_empty(): void
    {
        $clean = $this->sanitizer->sanitize('');
        $this->assertEquals('', $clean);
    }

    public function test_html5_figure_and_figcaption_preserved(): void
    {
        // Проверяем, что custom_definition реально работает и figure/figcaption сохраняются
        $html = '<figure><img src="https://example.com/image.jpg" alt="Image"><figcaption>Caption text</figcaption></figure>';
        $clean = $this->sanitizer->sanitize($html);

        // Проверяем, что figure и figcaption сохранились (custom_definition работает)
        $this->assertStringContainsString('<figure>', $clean);
        $this->assertStringContainsString('</figure>', $clean);
        $this->assertStringContainsString('<figcaption>', $clean);
        $this->assertStringContainsString('</figcaption>', $clean);
        $this->assertStringContainsString('<img', $clean);
        $this->assertStringContainsString('Caption text', $clean);
    }
}
```

**Описание:**

-   Unit-тесты для проверки санитизации HTML
-   Покрытие основных сценариев безопасности
-   Проверка удаления скриптов и сохранения безопасных тегов
-   Проверка автоматического добавления `rel="noopener noreferrer"`
-   Проверка добавления `noopener`/`noreferrer` к существующему `rel`
-   Проверка, что ссылки без `target="_blank"` не получают `rel="noopener noreferrer"`
-   Проверка сохранения HTML5 элементов `figure` и `figcaption` (подтверждает работу `custom_definition`)

---

## 7. tests/Feature/RichTextSanitizerIntegrationTest.php

**Статус:** НОВЫЙ ФАЙЛ

```php
<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RichTextSanitizerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ... setup ...

    public function test_entry_with_script_in_body_html_renders_safely(): void
    {
        // Создаем Entry с <script> в body_html
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => now(),
            'data_json' => [
                'body_html' => '<p>Hello</p><script>alert("XSS")</script><a href="https://example.com">Link</a>',
            ],
        ]);

        // Проверяем, что санитизированная версия создана
        $this->assertNotNull($entry->data_json['body_html_sanitized'] ?? null);

        // Проверяем, что скрипт удален из санитизированной версии
        $sanitized = $entry->data_json['body_html_sanitized'];
        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('alert', $sanitized);

        // Проверяем, что безопасные элементы сохранены
        $this->assertStringContainsString('<p>Hello</p>', $sanitized);
        $this->assertStringContainsString('<a href="https://example.com"', $sanitized);

        // Проверяем, что оригинальный body_html сохранен
        $this->assertStringContainsString('<script>alert("XSS")</script>', $entry->data_json['body_html']);
    }

    public function test_entry_with_figure_figcaption_preserved_in_sanitized(): void
    {
        // Проверяем, что custom_definition работает и figure/figcaption сохраняются в *_sanitized
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Figure Page',
            'slug' => 'figure-page',
            'status' => 'published',
            'published_at' => now(),
            'data_json' => [
                'body_html' => '<figure><img src="https://example.com/image.jpg" alt="Image"><figcaption>Image caption</figcaption></figure>',
            ],
        ]);

        // Проверяем, что figure и figcaption сохранились в санитизированной версии
        $sanitized = $entry->data_json['body_html_sanitized'] ?? '';
        $this->assertStringContainsString('<figure>', $sanitized);
        $this->assertStringContainsString('</figure>', $sanitized);
        $this->assertStringContainsString('<figcaption>', $sanitized);
        $this->assertStringContainsString('</figcaption>', $sanitized);
        $this->assertStringContainsString('<img', $sanitized);
        $this->assertStringContainsString('Image caption', $sanitized);

        // Проверяем, что оригинал сохранен
        $this->assertStringContainsString('<figure>', $entry->data_json['body_html']);
    }

    // ... другие тесты ...
}
```

**Описание:**

-   Интеграционные тесты для проверки санитизации в контексте Entry
-   Проверка создания санитизированной версии при создании Entry
-   Проверка обновления санитизированной версии при изменении data_json
-   Проверка сохранения оригинальных полей для трассируемости
-   Проверка сохранения HTML5 элементов `figure` и `figcaption` в `*_sanitized` (подтверждает работу `custom_definition`)

---

## Резюме изменений

### Созданные файлы:

1. `config/purifier.php` - конфигурация HTMLPurifier
2. `app/Domain/Sanitizer/RichTextSanitizer.php` - сервис санитизации
3. `tests/Unit/RichTextSanitizerTest.php` - unit-тесты
4. `tests/Feature/RichTextSanitizerIntegrationTest.php` - интеграционные тесты

### Измененные файлы:

1. `app/Observers/EntryObserver.php` - добавлена автоматическая санитизация
2. `app/Providers/AppServiceProvider.php` - регистрация RichTextSanitizer и создание директории кэша

### Ключевые особенности реализации:

-   ✅ Автоматическая санитизация при создании и обновлении Entry
-   ✅ Сохранение оригинальных и санитизированных версий HTML (обе версии хранятся)
-   ✅ Оптимизация производительности: санитизация только при изменении `data_json`
-   ✅ Полное покрытие тестами основных сценариев безопасности (unit + integration)
-   ✅ Защита от XSS через удаление скриптов и инлайновых обработчиков
-   ✅ Пост-обработка: автоматическое добавление `rel="noopener noreferrer"` для ссылок с `target="_blank"`
-   Проверка каждой ссылки индивидуально по исходному HTML
-   Добавление недостающих токенов к существующему `rel`, сохраняя существующие значения
-   Ссылки без `target="_blank"` не модифицируются
-   ✅ Поддержка HTML5 элементов `figure` и `figcaption` через `custom_definition`
-   ✅ Автоматическое создание директории кэша при загрузке приложения

### Требования для работы:

1. Установить пакет: `composer require mews/purifier`
2. Директория для кэша создается автоматически при загрузке приложения (в `AppServiceProvider::boot()`)

### Покрытие тестами:

**Unit-тесты (12 тестов):**

-   Удаление скриптов и сохранение безопасных тегов
-   Автоматическое добавление `rel="noopener noreferrer"` для ссылок с `target="_blank"`
-   Добавление недостающих токенов к существующему `rel`
-   Проверка, что ссылки без `target="_blank"` не модифицируются
-   Удаление `on*` атрибутов
-   Сохранение разрешенных тегов и схем URI
-   **Сохранение HTML5 элементов `figure` и `figcaption` (подтверждает работу `custom_definition`)**

**Feature-тесты (4 теста):**

-   Сквозная проверка: Entry с `<script>` → санитизированная версия без скрипта
-   Проверка добавления `rel="noopener noreferrer"` в контексте Entry
-   Проверка обновления санитизированной версии при изменении `data_json`
-   **Проверка сохранения `figure`/`figcaption` в `*_sanitized` (подтверждает работу `custom_definition`)**

**Всего:** 16 тестов, 49 assertions — все проходят успешно

### Технические детали:

-   **Doctype:** Используется `HTML 4.01 Transitional` (не HTML5)
-   HTMLPurifier не поддерживает HTML5 doctype напрямую в стандартной конфигурации
-   Доступные варианты: `HTML 4.01 Transitional`, `HTML 4.01 Strict`, `XHTML 1.0 Transitional`, `XHTML 1.0 Strict`, `XHTML 1.1`
-   `HTML 4.01 Transitional` выбран как наиболее совместимый вариант

-   **HTML5 элементы:** `figure` и `figcaption` добавляются через механизм `custom_definition`
-   `custom_definition` — специальный механизм mews/purifier для расширения HTMLDefinition
-   Позволяет добавлять кастомные HTML элементы, не поддерживаемые стандартным HTMLPurifier
-   Элементы объявляются с указанием типа контента, модели содержимого и атрибутов
-   Работоспособность подтверждается unit-тестом `test_html5_figure_and_figcaption_preserved` и feature-тестом `test_entry_with_figure_figcaption_preserved_in_sanitized`

-   **Блокировка `on*` атрибутов:** Через белый список атрибутов (`HTML.AllowedAttributes`)
-   HTMLPurifier не поддерживает `Attr.ForbiddenPatterns` в стандартной конфигурации
-   Блокировка достигается отсутствием `on*` атрибутов в белом списке разрешенных
-   Это безопасный и надежный подход, не зависящий от версии библиотеки

-   **Внешние изображения:** Разрешены через `URI.DisableExternalResources` => false (допустимы `<img>` с `https` схемами)
-   **Embed/iframe:** Остаются out-of-scope (будущий профиль `cms_embed`)

### Использование в шаблонах:

**⚠️ Критически важно:** Для рендера всегда используйте `*_sanitized` версии, никогда не используйте оригинальные поля напрямую!

**Рекомендуемый способ рендера в Blade:**

```blade
{!! $entry->data_json['body_html_sanitized'] ?? '' !!}
```

**Для excerpt:**

```blade
{!! $entry->data_json['excerpt_html_sanitized'] ?? '' !!}
```

Оригинальные поля `body_html` и `excerpt_html` сохраняются без изменений для трассируемости и возможного восстановления.
