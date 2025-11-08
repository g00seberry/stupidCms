# 61. Система компонентов (ядро)
---
owner: "@backend-team"
review_cycle_days: 90
last_reviewed: 2025-11-08
system_of_record: "code"
related_code:
  - "app/Support/Components/ComponentDefinition.php"
  - "app/Support/Components/ComponentRegistry.php"
  - "app/Support/Components/ComponentRenderer.php"
  - "app/Providers/ComponentsServiceProvider.php"
  - "config/components.php"
  - "resources/views/components/hero.blade.php"
  - "routes/web_content.php"
---

## Цель
Единое ядро для рендера «контентных компонентов/блоков» через реестр:
`{ slug, view, optional schema }` + Blade-директива для вызова.
Критерий: регистрация и рендер компонента `hero` работают.

## Объём
- Реестр компонентов (регистрация/поиск по `slug`).
- Рендерер, валидирующий `props` по опциональной `schema`.
- Blade-директива `@renderComponent('slug', [props])` (+ краткий алиас `@cmp(...)`).
- Конфиг для декларативной регистрации из кода/плагинов.
- Базовый компонент `hero` с простым шаблоном.

### Вне объёма
- Редактор компонентов в админке, версияция схем, i18n — позже.

---

## API ядра (в коде)

### 1) DTO: `ComponentDefinition`
`app/Support/Components/ComponentDefinition.php`
```php
<?php
declare(strict_types=1);

namespace App\Support\Components;

final class ComponentDefinition
{
    public function __construct(
        public string $slug,
        public string $view,
        public ?array $schema = null // Laravel validation rules или null
    ) {}
}
```

### 2) Реестр: `ComponentRegistry`
`app/Support/Components/ComponentRegistry.php`
```php
<?php
declare(strict_types=1);

namespace App\Support\Components;

use InvalidArgumentException;

final class ComponentRegistry
{
    /** @var array<string, ComponentDefinition> */
    private array $map = [];

    public function register(string $slug, string $view, ?array $schema = null): void
    {
        $slug = strtolower($slug);
        if (!preg_match('/^[a-z0-9][a-z0-9_\-]{1,63}$/', $slug)) {
            throw new InvalidArgumentException("Invalid component slug: {$slug}");
        }
        $this->map[$slug] = new ComponentDefinition($slug, $view, $schema);
    }

    public function has(string $slug): bool
    {
        return array_key_exists(strtolower($slug), $this->map);
    }

    public function get(string $slug): ComponentDefinition
    {
        $slug = strtolower($slug);
        if (!$this->has($slug)) {
            throw new InvalidArgumentException("Component not registered: {$slug}");
        }
        return $this->map[$slug];
    }
}
```

### 3) Рендерер: `ComponentRenderer`
`app/Support/Components/ComponentRenderer.php`
```php
<?php
declare(strict_types=1);

namespace App\Support\Components;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

final class ComponentRenderer
{
    public function __construct(
        private readonly ComponentRegistry $registry,
        private readonly ViewFactory $view
    ) {}

    /** @param array<string,mixed> $props */
    public function render(string $slug, array $props = []): HtmlString
    {
        $def = $this->registry->get($slug);

        if ($def->schema) {
            $v = Validator::make($props, $def->schema);
            if ($v->fails()) {
                throw new ValidationException($v);
            }
        }

        $html = $this->view->make($def->view, ['props' => $props, 'slug' => $def->slug])->render();
        return new HtmlString($html);
    }
}
```

### 4) Провайдер и Blade-директивы
`app/Providers/ComponentsServiceProvider.php`
```php
<?php
declare(strict_types=1);

namespace App\Providers;

use App\Support\Components\ComponentRegistry;
use App\Support\Components\ComponentRenderer;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

final class ComponentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComponentRegistry::class);
        $this->app->singleton(ComponentRenderer::class, function ($app) {
            return new ComponentRenderer($app->make(ComponentRegistry::class), $app['view']);
        });

        $this->mergeConfigFrom(base_path('config/components.php'), 'components');
    }

    public function boot(BladeCompiler $blade, ComponentRegistry $registry): void
    {
        // Регистрация из конфига
        foreach (config('components.register', []) as $def) {
            $registry->register($def['slug'], $def['view'], $def['schema'] ?? null);
        }

        // @renderComponent('hero', ['title' => '...'])
        $blade->directive('renderComponent', function ($expression) {
            return "<?php echo app(\App\Support\Components\ComponentRenderer::class)->render(...[$expression]); ?>";
        });

        // Короткий алиас: @cmp('hero', [...])
        $blade->directive('cmp', function ($expression) {
            return "<?php echo app(\App\Support\Components\ComponentRenderer::class)->render(...[$expression]); ?>";
        });
    }
}
```

### 5) Конфигурация
`config/components.php`
```php
<?php
return [
    'register' => [
        [
            'slug' => 'hero',
            'view' => 'components.hero',
            'schema' => [
                'title' => ['required', 'string', 'max:120'],
                'subtitle' => ['nullable', 'string', 'max:255'],
                'cta' => ['nullable', 'array'],
                'cta.label' => ['required_with:cta', 'string', 'max:50'],
                'cta.href'  => ['required_with:cta', 'url'],
            ],
        ],
    ],
];
```

### 6) Базовый компонент `hero`
`resources/views/components/hero.blade.php`
```blade
<section class="py-16">
  <div class="container mx-auto text-center">
    <h1 class="text-4xl font-bold">{{ $props['title'] }}</h1>
    @isset($props['subtitle'])
      <p class="mt-2 text-gray-600">{{ $props['subtitle'] }}</p>
    @endisset
    @isset($props['cta'])
      <a href="{{ $props['cta']['href'] }}" class="inline-block mt-6 px-4 py-2 rounded bg-black text-white">
        {{ $props['cta']['label'] }}
      </a>
    @endisset
  </div>
</section>
```

### 7) Использование в Blade
```blade
@renderComponent('hero', [
  'title' => 'Welcome to stupidCms',
  'subtitle' => 'Laravel 12 components system',
  'cta' => ['label' => 'Get started', 'href' => '/start']
])
{{-- или кратко --}}
@cmp('hero', ['title' => 'Minimal hero'])
```

---

## Инициализация
1) Подключить провайдер в `config/app.php` (если не авто-открывается через package discovery):
```php
'providers' => [
    // ...
    App\Providers\ComponentsServiceProvider::class,
],
```
2) Создать `config/components.php`, положить `hero`.
3) Добавить blade-шаблон `resources/views/components/hero.blade.php`.
4) В нужном месте шаблонов вызвать `@renderComponent('hero', [...])`.

---

## Ошибки и контракты
- Неизвестный `slug` → `InvalidArgumentException("Component not registered")` → 500 (или перехватить и отобразить заглушку).
- Невалидные `props` при наличии `schema` → `ValidationException` (422) в контроллере, который рендерит страницу.
- `view` отсутствует → стандартная ошибка View.

---

## Тесты (Pest)

### Unit — реестр
```php
it('registers and resolves component', function () {
    $r = new \App\Support\Components\ComponentRegistry();
    $r->register('hero', 'components.hero');
    expect($r->has('hero'))->toBeTrue();
    $def = $r->get('hero');
    expect($def->view)->toBe('components.hero');
});
```

### Feature — рендер hero
```php
it('renders hero component', function () {
    $html = app(\App\Support\Components\ComponentRenderer::class)
        ->render('hero', ['title' => 'Hi'])->toHtml();

    expect($html)->toContain('Hi');
});
```

### Feature — blade-директива
```php
it('renders via blade directive', function () {
    $view = view('test-page', ['t' => 'Hello']); // test-page использует @cmp('hero', ['title' => $t])
    $out = $view->render();
    expect($out)->toContain('Hello');
});
```

### Feature — валидация схемы
```php
it('validates props against schema', function () {
    app(\App\Support\Components\ComponentRegistry::class)
        ->register('x', 'components.hero', ['title' => ['required', 'string']]);
    app(\App\Support\Components\ComponentRenderer::class)
        ->render('x', []); // throws
})->throws(\Illuminate\Validation\ValidationException::class);
```

---

## Приёмка
- [x] Компонент `hero` зарегистрирован через `config/components.php`.
- [x] `@renderComponent('hero', {...})` выводит ожидаемую разметку.
- [x] При некорректных `props` срабатывает валидация (если `schema` задана).
- [x] Директива-алиас `@cmp()` доступна.

---

## Чек-лист к PR
- [ ] Провайдер зарегистрирован; конфиг добавлен.
- [ ] `ComponentRegistry`/`ComponentRenderer` покрыты тестами.
- [ ] Базовый `hero`-шаблон присутствует.
- [ ] Документация «как добавить новый компонент» (короткий HOWTO в `/docs/20-how-to/add-component.md`).

