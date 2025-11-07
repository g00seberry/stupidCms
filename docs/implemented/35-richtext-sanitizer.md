# Санитайзер richtext (Задача 35)

## Обзор

Интегрирован серверный HTML‑санитайзер (HTMLPurifier) через пакет `mews/purifier` и создан сервис `RichTextSanitizer` для очистки richtext‑полей перед сохранением в базу данных. Санитизация применяется автоматически в `EntryObserver` при создании и обновлении записей.

**Ключевые особенности:**

- Автоматическая санитизация `body_html` и `excerpt_html` из `data_json` при сохранении Entry
- Очищенный HTML сохраняется в `body_html_sanitized` и `excerpt_html_sanitized`
- Базовый профиль `cms_default` разрешает безопасные теги и атрибуты
- Полное удаление `<script>` тегов и `on*` атрибутов
- Удаление небезопасных атрибутов (например, `target="_blank"`)
- Разрешены только безопасные схемы URI: `http`, `https`, `mailto`

**Дата реализации:** 2025-01-XX  
**Статус:** ✅ Реализовано, все тесты проходят

---

## Структура файлов

```
app/
├── Domain/
│   └── Sanitizer/
│       └── RichTextSanitizer.php      # Сервис санитизации
├── Observers/
│   └── EntryObserver.php               # Обновлен: добавлена санитизация
└── Providers/
    └── AppServiceProvider.php           # Регистрация RichTextSanitizer

config/
└── purifier.php                         # Конфигурация HTMLPurifier с профилем cms_default

tests/
└── Unit/
    └── RichTextSanitizerTest.php        # Unit-тесты санитайзера
```

---

## Основные компоненты

### 1. Конфигурация: `config/purifier.php`

Конфигурационный файл для HTMLPurifier с профилем `cms_default`.

**Разрешенные теги:**
- Текстовые: `a`, `abbr`, `b`, `blockquote`, `br`, `code`, `em`, `i`, `hr`, `p`, `pre`, `s`, `small`, `strong`, `sub`, `sup`, `u`
- Списки: `li`, `ol`, `ul`
- Заголовки: `h1`, `h2`, `h3`, `h4`, `h5`, `h6`
- Медиа: `img`, `figure`, `figcaption`

**Разрешенные атрибуты:**
- Для ссылок: `href`, `title`, `target`, `rel`
- Для изображений: `src`, `alt`, `title`, `width`, `height`

**Безопасность:**
- Запрещены все `<script>` теги
- Запрещены все `on*` атрибуты (onclick, onload и т.д.) через белый список атрибутов (`HTML.AllowedAttributes`)
  - HTMLPurifier не поддерживает `Attr.ForbiddenPatterns` в стандартной конфигурации
  - Блокировка достигается отсутствием `on*` атрибутов в белом списке разрешенных
- Запрещены ID атрибуты (`Attr.EnableID` => false)
- Разрешены только схемы: `http`, `https`, `mailto`
- Запрещены CSS стили в базовом профиле
- Внешние изображения разрешены (`URI.DisableExternalResources` => false) — допустимы `<img>` с `https` схемами
- Embed/iframe остаются out-of-scope (будущий профиль `cms_embed`)

**Пример конфигурации:**

```php
'cms_default' => [
    'HTML.Doctype' => 'HTML 4.01 Transitional',
    'HTML.AllowedElements' => ['a','b','p','h1','figure','figcaption',...],
    'HTML.AllowedAttributes' => ['a.href','a.title','a.target','a.rel',...],
    'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
    'URI.DisableExternalResources' => false, // Разрешены внешние изображения
    // onload, onclick и т.д. блокируются через белый список атрибутов
],
'custom_definition' => [
    'id' => 'cms_default_html5',
    'rev' => 1,
    'elements' => [
        ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
        ['figcaption', 'Inline', 'Flow', 'Common'],
    ],
]
```

**Doctype и HTML5 элементы:**

- **Doctype:** Используется `HTML 4.01 Transitional` (не HTML5)
  - HTMLPurifier не поддерживает HTML5 doctype напрямую в стандартной конфигурации
  - Доступные варианты: `HTML 4.01 Transitional`, `HTML 4.01 Strict`, `XHTML 1.0 Transitional`, `XHTML 1.0 Strict`, `XHTML 1.1`
  - `HTML 4.01 Transitional` выбран как наиболее совместимый вариант

- **HTML5 элементы:** `figure` и `figcaption` добавляются через механизм `custom_definition`
  - `custom_definition` — специальный механизм mews/purifier для расширения HTMLDefinition
  - Позволяет добавлять кастомные HTML элементы, не поддерживаемые стандартным HTMLPurifier
  - Элементы объявляются с указанием типа контента, модели содержимого и атрибутов
  - Работоспособность подтверждается unit-тестом `test_html5_figure_and_figcaption_preserved` и feature-тестом `test_entry_with_figure_figcaption_preserved_in_sanitized`

### 2. Сервис: `app/Domain/Sanitizer/RichTextSanitizer.php`

Сервис для санитизации HTML контента.

**Методы:**

- `sanitize(string $html): string` - очищает HTML и возвращает безопасную версию

**Особенности:**

- Использует профиль `cms_default` из конфигурации
- HTMLPurifier автоматически удаляет небезопасные атрибуты и схемы
- Пост-обработка: автоматически добавляет `rel="noopener noreferrer"` для ссылок с `target="_blank"`
  - Проверяет каждую ссылку индивидуально по исходному HTML (до санитизации)
  - Если в исходном HTML была `target="_blank"`, гарантирует наличие `noopener` и `noreferrer` в `rel`
  - Если `rel` уже существует, добавляет недостающие токены, сохраняя существующие значения
  - Ссылки без `target="_blank"` не модифицируются
- Singleton регистрация в контейнере

**Пример использования:**

```php
$sanitizer = app(RichTextSanitizer::class);
$clean = $sanitizer->sanitize('<p>Hello<script>alert(1)</script></p>');
// Результат: '<p>Hello</p>'
```

### 3. Интеграция в EntryObserver

Санитизация применяется автоматически при создании и обновлении Entry.

**Точки применения:**

1. **При создании** (`creating`): санитизируются все richtext поля
2. **При обновлении** (`updating`): санитизируются только при изменении `data_json`

**Обрабатываемые поля:**

- `data_json['body_html']` → сохраняется в `data_json['body_html_sanitized']` (оригинал остается)
- `data_json['excerpt_html']` → сохраняется в `data_json['excerpt_html_sanitized']` (оригинал остается)

**Важно:** Обе версии сохраняются — оригинальная для трассируемости, санитизированная для рендера.

**Пример:**

```php
// До санитизации
$entry->data_json = [
    'body_html' => '<p>Hello<script>alert(1)</script></p>'
];

// После сохранения (автоматически)
$entry->data_json = [
    'body_html' => '<p>Hello<script>alert(1)</script></p>', // Оригинал сохранен
    'body_html_sanitized' => '<p>Hello</p>' // Санитизированная версия для рендера
];
```

### 4. Регистрация в контейнере

**Файл:** `app/Providers/AppServiceProvider.php`

```php
$this->app->singleton(RichTextSanitizer::class);
```

---

## Использование в шаблонах

**Важно:** Храним обе версии — оригинальную (`body_html`) и санитизированную (`body_html_sanitized`). Для рендера используем **только санитизированную версию** (`*_sanitized`).

**Рекомендуемый способ рендера в Blade:**

```blade
{!! $entry->data_json['body_html_sanitized'] ?? '' !!}
```

**Для excerpt:**

```blade
{!! $entry->data_json['excerpt_html_sanitized'] ?? '' !!}
```

**Альтернативный вариант с проверкой:**

```blade
@php
  $html = data_get($entry->data_json, 'body_html_sanitized');
  $bodyHtml = data_get($entry->data_json, 'body_html');
@endphp

@if($html !== null)
  {{-- Санитизированный HTML (используем для рендера) --}}
  {!! $html !!}
@elseif($bodyHtml !== null)
  {{-- Fallback: временно экранируем до включения санитайзера --}}
  {{ $bodyHtml }}
@endif
```

**⚠️ Критически важно:** Никогда не используйте `{!! $entry->data_json['body_html'] !!}` напрямую — это небезопасно! Всегда используйте `*_sanitized` версии для рендера.

**Примечание:** Оригинальные поля `body_html` и `excerpt_html` сохраняются без изменений для трассируемости и возможного восстановления. В рендере используем только `*_sanitized` версии.

---

## Критерии приёмки

✅ **Пакет установлен, опубликована конфигурация**

- Пакет `mews/purifier` установлен
- Конфигурация `config/purifier.php` создана

✅ **Добавлен профиль `cms_default` с белым списком тегов/атрибутов**

- Профиль содержит разрешенные теги и атрибуты
- Запрещены скрипты и инлайновые обработчики

✅ **Реализован сервис `RichTextSanitizer` и внедрён в сохранение Entry**

- Сервис создан в `app/Domain/Sanitizer/RichTextSanitizer.php`
- Интегрирован в `EntryObserver` для автоматической санитизации
- Зарегистрирован в `AppServiceProvider`

✅ **Юнит‑тест подтверждает: `<script>` удаляется, `<a href>` остаётся**

- Тесты проходят успешно
- Проверены основные сценарии безопасности

---

## Тесты

### Unit тесты

**Файл:** `tests/Unit/RichTextSanitizerTest.php`

**Покрытие:**

1. ✅ **test_script_removed_and_anchor_kept** - `<script>` удаляется, `<a href>` сохраняется
2. ✅ **test_target_blank_gets_rel_noopener** - автоматическое добавление `rel="noopener noreferrer"`
3. ✅ **test_target_blank_with_existing_rel_preserved** - сохранение существующего rel
4. ✅ **test_onclick_attribute_removed** - удаление `onclick` и других `on*` атрибутов
5. ✅ **test_allowed_tags_preserved** - сохранение разрешенных тегов
6. ✅ **test_javascript_scheme_removed** - удаление `javascript:` схем
7. ✅ **test_http_https_schemes_allowed** - разрешение `http` и `https` схем
8. ✅ **test_mailto_scheme_allowed** - разрешение `mailto` схем
9. ✅ **test_img_tag_with_allowed_attributes** - сохранение изображений с разрешенными атрибутами
10. ✅ **test_empty_string_returns_empty** - обработка пустых строк
11. ✅ **test_html5_figure_and_figcaption_preserved** - сохранение HTML5 элементов figure/figcaption (подтверждает работу custom_definition)

**Результаты:** 11 passed

---

## Связанные задачи

- **Задача 31**: PageController (использует `body_html_sanitized` в шаблонах)
- **Задача 33**: Blade шаблоны (рендер санитизированного HTML)
- **Задача 60**: Admin: WYSIWYG (редактор будет сохранять в `body_html`, санитизация применится автоматически)
- **Задача 62**: Media embeds (может потребоваться расширенный профиль для iframe)
- **Задача 70**: XSS‑защита в комментариях/формах (может использовать тот же санитайзер)

---

## Нефункциональные аспекты

### Производительность

- **Кэш HTMLPurifier**: включен сериализованный кэш в `storage/app/purifier` для ускорения очистки
- **Санитизация только при изменении**: в `EntryObserver` санитизация применяется только при изменении `data_json`
- **Singleton регистрация**: сервис регистрируется как singleton для переиспользования

### Надёжность

- **Graceful degradation**: если HTMLPurifier недоступен, приложение не сломается (но санитизация не будет работать)
- **Типизация**: строгая типизация методов
- **Обработка пустых значений**: корректная обработка пустых строк и null значений

### Безопасность

- **XSS защита**: полное удаление `<script>` тегов и `on*` атрибутов
- **Схемы URI**: только безопасные схемы (`http`, `https`, `mailto`)
- **Defense in depth**: санитизация на уровне сохранения + экранирование в шаблонах (для обратной совместимости)
- **Автоматический noopener**: защита от tabnabbing через автоматическое добавление `rel="noopener noreferrer"`

### Тестируемость

- **Unit тесты**: полное покрытие основных сценариев
- **Dependency Injection**: легко мокировать для тестирования
- **Изоляция**: санитизация не зависит от внешних сервисов (кроме HTMLPurifier)

---

## Расширение (out of scope)

### Профиль `cms_embed` для iframe

Возможное расширение для поддержки встраивания видео (YouTube/Vimeo):

```php
'cms_embed' => [
    // ... наследует cms_default ...
    'HTML.SafeIframe' => true,
    'URI.SafeIframeRegexp' => '%^(https?:)?//(www\.)?(youtube|vimeo)\.com%',
]
```

### Мягкое "причесывание" разметки

- Автоматическое форматирование таблиц
- Нормализация списков
- Тонкая конфигурация CSS (разрешение определенных стилей)

### Отложенная санитизация

Для больших документов можно использовать очереди:

```php
SanitizeEntryJob::dispatch($entry);
```

---

## Установка

1. Установить пакет:
```bash
composer require mews/purifier
```

2. Опубликовать конфигурацию (если нужно):
```bash
php artisan vendor:publish --provider="Mews\Purifier\PurifierServiceProvider" --tag=config
```

3. Создать директорию для кэша:
```bash
mkdir -p storage/app/purifier
```

---

## Примеры использования

### Прямое использование сервиса

```php
use App\Domain\Sanitizer\RichTextSanitizer;

$sanitizer = app(RichTextSanitizer::class);
$clean = $sanitizer->sanitize($dirtyHtml);
```

### Проверка санитизированного контента

```php
$entry = Entry::find($id);
$sanitized = data_get($entry->data_json, 'body_html_sanitized');
if ($sanitized) {
    echo $sanitized; // Безопасный HTML
}
```

### Импорт/сид данных

При импорте данных санитизация применяется автоматически через `EntryObserver`:

```php
Entry::create([
    'data_json' => [
        'body_html' => '<p>Content<script>alert(1)</script></p>'
    ]
]);
// После создания body_html_sanitized будет содержать '<p>Content</p>'
```

---

## Диагностика и отладка

### Проверка санитизации

```php
$sanitizer = app(RichTextSanitizer::class);
$before = '<p>Hello<script>alert(1)</script></p>';
$after = $sanitizer->sanitize($before);
// $after = '<p>Hello</p>'
```

### Проверка конфигурации

```php
$config = config('purifier.settings.cms_default');
dd($config);
```

### Очистка кэша HTMLPurifier

```bash
rm -rf storage/app/purifier/*
```

---

## Заключение

Реализация задачи 35 обеспечивает автоматическую санитизацию richtext полей при сохранении записей, защищая приложение от XSS атак. Интеграция в `EntryObserver` гарантирует, что весь контент очищается перед сохранением в базу данных, а использование проверенного пакета HTMLPurifier обеспечивает надежность и производительность.

