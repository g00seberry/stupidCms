# Задача 21 — Сервис транслитерации RU→lat (slugify)

**Цель:** дать единый, расширяемый и детерминированный способ генерировать URL-слуги из русских (и смешанных) строк: нормализовать текст, транслитерировать **RU→lat**, убрать пунктуацию/мусор, склеить через дефис, а при конфликте — аккуратно **дедуплицировать** `-2`, `-3`, … (пример: «Страница» → `stranica`, следующий конфликт — `stranica-2`).

---

## 1) Область применения и связи

- Используется ядром при создании/редактировании `Entry` (тип `page` и др.), формируя/обновляя поле `slug`. В случае изменения — история слугов и редиректы обрабатываются соседними задачами.
- Инварианты уникальности и проверки конфликтов дополняются задачами **#22** (уникальность среди Pages) и **#23** (конфликт с `reserved_routes`). Сервис slugify должен предоставить удобные **хуки/интерфейсы** для этих проверок.

---

## 2) Функциональные требования (расширенные)

**База (из плана задач):**
- Реализовать `slugify()` с:
  1) **нормализацией** текста;  
  2) **заменами `ё/й/ь`** и общим RU→lat маппингом;  
  3) **удалением пунктуации** и мусорных символов;  
  4) **дедупликацией** результата.  
  Тесты: «Страница → `stranica`», конфликт → `stranica-2`.

**Дополнительно (для гибкости):**
- Настраиваемые опции **транслитерации, нормализации, длины, разделителя, стоп-слов, исключений, стратегии дедупа**.
- Публичный **контракт интерфейса** (DI), конфиг `config/stupidcms.php: slug`, события/фильтры для плагинов.
- Вспомогательный сервис **уникализации** c безопасной работой при гонках записей (retry по уникальному индексу).
- **Admin SPA**: эндпоинт превью `/api/v1/admin/utils/slugify` (истинный результат всегда серверный).

---

## 3) Публичный контракт (PHP, Laravel)

```php
namespace App\Support\Slug;

final class SlugOptions
{
    public function __construct(
        public string  $delimiter = '-',
        public bool    $toLower = true,
        public bool    $asciiOnly = true,
        public int     $maxLength = 120,
        public string  $scheme = 'ru_basic',     // имя набора правил
        public array   $customMap = [],          // локальные переопределения маппинга
        public array   $stopWords = [],          // слова, которые надо убрать
        public array   $reserved = [],           // зарезервированные конкретные слуги
        public ?callable $postProcess = null,    // фильтр пост-обработки строки
    ) {}
}

interface Slugifier
{
    public function slugify(string $source, ?SlugOptions $opts = null): string;
}

interface UniqueSlugService
{
    /**
     * @param callable $isTaken function(string $slug): bool  — проверка занятости (в т.ч. reserved routes)
     * @param int $startFrom индекс с которого начинаем суффикс (обычно 2)
     */
    public function ensureUnique(string $slug, callable $isTaken, int $startFrom = 2): string;
}
```

**DI и конфиг:**
- Сервис-провайдер `SlugServiceProvider` биндит `Slugifier::class` и `UniqueSlugService::class`.  
- Конфиг `config/stupidcms.php` → секция `slug` (см. §6).

---

## 4) Алгоритм `slugify()` (детально)

1) **Unicode-нормализация**: NFKD; trim; collapse whitespace.  
2) **Кейсы/регистр**: опция `$toLower` → `mb_strtolower` (ru_RU).  
3) **Транслит RU→lat** (см. маппинг ниже): построчно заменяем, начиная с диграфов/диграмм.  
4) **Спец-замены**:  
   - `ё` → `e` (по умолчанию);  
   - `й` → `i` (в конце слова — тоже `i`);  
   - `ь`/`ъ` удаляются.  
   (Все правила можно переопределить в конфиге/`customMap`.)  
5) **Очистка**: разрешаем `[A-Za-z0-9]` + пробелы/`_`/`-`; остальное удаляем.  
6) **Дефисы**: пробелы/`_` → разделитель (по умолчанию `-`); сжать повторяющиеся дефисы.  
7) **Стоп-слова** (опционально): удаляем одиночные токены из `$stopWords`.  
8) **Обрезка**: до `$maxLength`, безопасно относительно границ токенов; потом убрать ведущие/замыкающие дефисы.  
9) **Пост-процессор** (если задан): кастомная функция на финальной строке.

Результат — **ASCII-строка** (если `$asciiOnly=true`), безопасная для URL и соответствующая требованиям проекта про RU→lat + уникализацию.

---

## 5) Маппинг по умолчанию (схема `ru_basic`)

> Полностью переопределяемо в конфиге/плагинах.

| RU | lat | RU | lat | RU | lat |
|---|---|---|---|---|---|
| а | a | ж | zh | ш | sh |
| б | b | з | z  | щ | shch |
| в | v | и | i  | ъ | *(удалить)* |
| г | g | й | i  | ы | y |
| д | d | к | k  | ь | *(удалить)* |
| е | e | л | l  | э | e |
| ё | e | м | m  | ю | yu |
| ж | zh| н | n  | я | ya |
| ч | ch| о | o  | х | kh |
| ц | ts| п | p  | йо/ье | io/ie (как кастом, если нужно) |
| ф | f | р | r  | йе/йа | ie/ia (кастом) |
| т | t | с | s  |   |   |
| у | u | т | t  |   |   |

**Особые правила:**  
- `й` → `i` (унифицированно), чтобы избежать «yoga/yandex»-микса; при желании можно переключить на `y`.  
- `ё` → `e` (часто ожидаемое SEO-упрощение).  
- `ь`/`ъ` — глушатся.  
- Комбинации (`ш`, `щ`, `ж`, `ч`, `ц`, `х`) — устойчивые.  

Для «брендов»/топонимов задаём **исключения**: `['йога' => 'yoga', 'Санкт-Петербург' => 'sankt-peterburg']`.

---

## 6) Конфигурация (`config/stupidcms.php` фрагмент)

```php
'slug' => [
    'default' => [
        'delimiter'  => '-',
        'toLower'    => true,
        'asciiOnly'  => true,
        'maxLength'  => 120,
        'scheme'     => 'ru_basic',
        'stopWords'  => ['и','в','на'],   // опционально
        'reserved'   => [],               // конкретные слуги под запретом
    ],
    'schemes' => [
        'ru_basic' => [
            'map' => [ 'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'i','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya' ],
            'exceptions' => [ /* 'йога' => 'yoga' */ ],
        ],
        // можно добавить 'gost', 'yandex' и т.п.
    ],
],
```

Плагины могут модифицировать карту/правила через **filter-хуки**.

---

## 7) Уникализация (дедуп) и безопасность гонок

**Задача:** если `stranica` занята в области (например, `post_type=page`), получить `stranica-2`, затем `stranica-3`, … — предсказуемо и без гонок.

**API:**
```php
$unique = $uniqueSlugService->ensureUnique(
    $base,
    isTaken: fn(string $slug) => $repo->slugExists($slug, scope: ['post_type' => 'page']) || $reserved->conflicts($slug)
);
// -> 'stranica' или 'stranica-2'
```

**Стратегия:**
1) Пробуем `base`. Если занято — добавляем `-2`, `-3`, …  
2) Ограничиваем **диапазон попыток** (например, до 10 000) и длину: если `maxLength` мешает, обрезаем основу так, чтобы `-NNN` влез.  
3) **Гонки:** окончательный страж — **UNIQUE-ограничение** в БД внутри транзакции (создаём запись → при конфликте ловим и повторяем с новым суффиксом).  
4) Для Pages также учитываем задачу #22 (уникальность в скоупе `page`).  
5) Для конфликтов с **зарезервированными** путями — используем сервис `reserved_routes` как один из `isTaken`-чекеров.

---

## 8) Точки интеграции в приложении

- **Eloquent Observer `EntryObserver`**  
  - При `creating`/`updating` (если заголовок/slug изменились):  
    1) Если пользователь задал кастомный slug — прогоняем через *мягкий* `slugify` (не портим вручную введённые латиницей, но чистим/нормализуем).  
    2) Если slug пуст — генерируем из `title`.  
    3) Прогоняем через `UniqueSlugService` с скоупом типа записи и проверкой `reserved_routes`.  
- **Admin SPA**  
  - Поле `title` → дебаунс-запрос к `/api/v1/admin/utils/slugify?title=...&postType=page`, чтобы показать **превью**.  
  - При сохранении всё равно идёт серверная валидация и окончательная генерация (истина — на бэкенде).  
- **Плагины**  
  - Могут:  
    - добавлять схемы/карты транслита;  
    - фильтровать итоговый slug (например, применять брендовые правила);  
    - вводить дополнительные «занятые» значения (например, свои маршруты).

---

## 9) Маршруты и валидация

- **В публичном роутинге** слуги используются напрямую в `/{slug}`, поэтому любой конфликт должен быть исключён заранее через уникализацию и проверку зарезервированных путей.  
- **Валидация API** сочетается с задачами #22/23:  
  - Rule `uniquePageSlug` (скоуп `page`).  
  - Rule `notReservedRoute` (case-insensitive).

---

## 10) Примеры поведения

| Вход | Результат | Комментарий |
|---|---|---|
| `Страница` | `stranica` | Базовый тест. |
| `Страница` (уже занято) | `stranica-2` | Дедуп по числовому суффиксу. |
| `Йога и чай` | `ioga-i-chai` | `й`→`i`, стоп-слово `и` выкидывается если добавлено в `stopWords`. |
| `  !!! Привет,—мир !!!  ` | `privet-mir` | Очистка пунктуации + дефисы. |
| `Очень-очень длинный заголовок ...` | `ochen-ochen-dlinnyj-zagolovok-...` (обрезан ≤ 120) | Safe trim по токенам. |
| `admin` | *(422)* | Запрещён как зарезервированный путь. |

---

## 11) Набросок реализации

```php
final class DefaultSlugifier implements Slugifier
{
    public function __construct(private array $config) {}

    public function slugify(string $src, ?SlugOptions $opts = null): string
    {
        $o = $opts ?? new SlugOptions(...$this->config['slug']['default']);
        $scheme = $this->config['slug']['schemes'][$o->scheme] ?? ['map'=>[], 'exceptions'=>[]];

        $s = trim($src);
        if ($s === '') return '';

        // 1) Unicode NFKD + lower
        $s = \Normalizer::normalize($s, \Normalizer::FORM_KD) ?: $s;
        if ($o->toLower) $s = mb_strtolower($s, 'UTF-8');

        // 2) Exceptions (whole-word)
        foreach ($scheme['exceptions'] as $ru => $lat) {
            $s = preg_replace('/\\b'.preg_quote(mb_strtolower($ru)).'\\b/u', $lat, $s);
        }

        // 3) Map RU→lat (longest first)
        $map = array_merge($scheme['map'], $o->customMap);
        uksort($map, fn($a,$b) => mb_strlen($b,'UTF-8') <=> mb_strlen($a,'UTF-8'));
        $s = strtr($s, $map);

        // 4) asciiOnly cleanup
        $s = preg_replace('~[^a-z0-9\-_ ]+~i', '', $s);
        $s = preg_replace('~[ _]+~', $o->delimiter, $s);
        $s = preg_replace('~'.$o->delimiter.'{2,}~', $o->delimiter, $s);
        $s = trim($s, $o->delimiter);

        // 5) stop-words (optional, token-wise)
        if (!empty($o->stopWords)) {
            $tokens = array_filter(explode($o->delimiter, $s), fn($t) => !in_array($t, $o->stopWords, true));
            $s = implode($o->delimiter, $tokens);
        }

        // 6) max length (try cut at token boundaries)
        if ($o->maxLength > 0 && strlen($s) > $o->maxLength) {
            $s = substr($s, 0, $o->maxLength);
            $s = rtrim($s, $o->delimiter);
        }

        // 7) post-process
        if ($o->postProcess) $s = ($o->postProcess)($s, $o);

        return $o->toLower ? strtolower($s) : $s;
    }
}

final class DefaultUniqueSlugService implements UniqueSlugService
{
    public function ensureUnique(string $base, callable $isTaken, int $startFrom = 2): string
    {
        $slug = $base;
        $i = $startFrom;
        while ($isTaken($slug)) {
            $slug = preg_match('~-\d+$~', $base) ? preg_replace('~-\d+$~', "-{$i}", $base) : "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }
}
```

> На уровне репозитория/сервиса Entry обязательно финальное подтверждение уникальности в транзакции — опираемся на существующие инварианты уникальности slug и запреты конфликтов.

---

## 12) Юнит-тесты (пример PHPUnit)

```php
public function test_basic_slugification(): void
{
    $slug = $this->slugifier->slugify('Страница');
    $this->assertSame('stranica', $slug); // критерий приёмки
}

public function test_dedupe_conflict(): void
{
    $base = $this->slugifier->slugify('Страница'); // 'stranica'
    $unique = $this->unique->ensureUnique($base, fn($s) => in_array($s, ['stranica'], true));
    $this->assertSame('stranica-2', $unique); // критерий приёмки
}

public function test_clean_punctuation_and_spaces(): void
{
    $slug = $this->slugifier->slugify('  !!! Привет,—мир !!!  ');
    $this->assertSame('privet-mir', $slug);
}

public function test_stop_words_and_max_length(): void
{
    $opts = new SlugOptions(stopWords:['i','v','na'], maxLength: 20);
    $slug = $this->slugifier->slugify('Йога и чай — в наилучшем формате', $opts);
    $this->assertStringStartsWith('ioga-chai', $slug);
}
```

---

## 13) Admin SPA и API

- **GET** `/api/v1/admin/utils/slugify?title=...&postType=page` → `{ base: "stranica", unique: "stranica" }`  
- **Ответ при конфликте**: `{ base: "stranica", unique: "stranica-2" }`  
- В редакторе Page: автоподстановка slug с возможностью ручного редактирования; при сохранении сервер финализирует.

---

## 14) Расширяемость и DX

- **Плагины**: регистрируют собственные схемы (`schemes`) и исключения для домена/бренда.  
- **Хуки**: `filters.slug.map`, `filters.slug.output`, `filters.slug.stopWords`.  
- **Конфиг-override** через `.env` для ключевых опций (`SLUG_MAX_LENGTH`, `SLUG_DELIMITER` и т.д.).  
- **Трейты/хелперы**: `HasSlug` для моделей; `slugify()` фасад/хелпер для быстрой генерации.

---

## 15) Крайние случаи и правила

- Пустой результат → возвращаем пустую строку (валидация API не пропустит публикацию без slug).  
- Только цифры → допустимо (`"2025"`).  
- Повтор дефисов/пробелов → схлопывание.  
- Эмодзи/иное → удаляем при `asciiOnly=true`.  
- Длина суффикса всегда учитывается при обрезке.

---

## 16) Как подключать/использовать (шаблон)

```php
// Service Provider (AppServiceProvider or dedicated SlugServiceProvider)
$this->app->singleton(Slugifier::class, fn($app) => new DefaultSlugifier(config('stupidcms')));
$this->app->singleton(UniqueSlugService::class, DefaultUniqueSlugService::class);

// В Observer
public function saving(Entry $e) {
    if (!$e->slug) {
        $base = $this->slugifier->slugify($e->title);
        $e->slug = $this->unique->ensureUnique($base, fn($s) =>
            $this->entries->existsSlug($s, postType:'page') || $this->reserved->conflicts($s)
        );
    }
}
```

---

## 17) Почему так

- Поддерживаем **авто-транслит RU→lat** и **уникализацию `-2`** согласно URL-модели CMS.  
- Явная интеграция с **уникальностью среди Pages (#22)** и **reserved routes (#23)**.  
- Вписывается в общую архитектуру (fallback-роут `/slug`, история слугов/редиректы), не ломая инварианты БД.

