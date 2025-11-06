# stupidCms — исчерпывающая спецификация системы

> Laravel-бэкенд + React/TS/MobX-админка, Blade/гибридный фронтенд, плагины, медиа-пайплайн, таксономии, компоненты/partials, cookie-based JWT, CSRF cookie, ElasticSearch (RU), redirects, SEO/Blocks/Shop плагины. Многозадачность и мультиязычность **не нужны**. Опсы — максимально простые.

---

## Содержание
- [1) Цели и позиционирование](#1-цели-и-позиционирование)
- [2) Технологии и границы](#2-технологии-и-границы)
- [3) Доменные сущности (обзор)](#3-доменные-сущности-обзор)
- [4) URL-модель и публикация](#4-url-модель-и-публикация)
- [5) Компоненты, partials и шаблоны](#5-компоненты-partials-и-шаблоны)
- [6) Таксономии](#6-таксономии)
- [7) Медиа-платформа](#7-медиа-платформа)
- [8) Поиск (ElasticSearch)](#8-поиск-elasticsearch)
- [9) Безопасность и доступ](#9-безопасность-и-доступ)
- [10) REST API (высокоуровневый обзор)](#10-rest-api-высокоуровневый-обзор)
- [11) Веб-маршрутизация (public)](#11-веб-маршрутизация-public)
- [12) Админка (ReactTSMobX)](#12-админка-reacttsmobx)
- [13) Плагины и расширяемость](#13-плагины-и-расширяемость)
- [14) Хранилище данных (MySQL 8) — ключевые таблицы](#14-хранилище-данных-mysql-8--ключевые-таблицы)
- [15) Производительность и кэш](#15-производительность-и-кэш)
- [16) Качество и тестирование](#16-качество-и-тестирование)
- [17) Безопасность (детализация)](#17-безопасность-детализация)
- [18) Плагины (модель взаимодействия)](#18-плагины-модель-взаимодействия)
- [19) Фронтенд (public) — Blade/гибрид](#19-фронтенд-public--bladeгибрид)
- [20) Ограничения и известные решения](#20-ограничения-и-известные-решения)
- [21) Что входит в MVP](#21-что-входит-в-mvp)
- [22) Приложение: соответствие требованиям](#22-приложение-соответствие-требованиям)

---

## 1) Цели и позиционирование

**stupidCms** — лёгкая, расширяемая CMS на базе Laravel с:
- кастомными типами записей и полями (JSON-подход вместо EAV);
- унифицированной системой компонентов (partials = components без явной схемы);
- встроенной библиотекой медиа и трансформациями;
- таксономиями (категории/теги);
- системой плагинов (Composer + автодискавери из `/plugins`) с хуками;
- плоскими URL для страниц (`/{slug}`), историей слугов и авторедиректами;
- безопасной админкой: cookie-based JWT + отдельный CSRF cookie;
- полнотекстовым поиском в ElasticSearch (анализатор **russian**).

Сценарий: «Page» — базовый тип из ядра. «Product» добавляет плагин **Shop**. SEO, Blocks, Redirects — отдельные плагины.

---

## 2) Технологии и границы

- **Backend**: PHP 8.2+, Laravel 10/11.
- **DB**: MySQL 8.0+ (поддержка PostgreSQL описана, но миграции сгенерированы под MySQL).
- **Frontend (public)**: Blade-шаблоны (гибридная модель: Blade + компоненты).
- **Admin SPA**: React 18 + TypeScript + Vite + MobX + React Router.
- **Search**: ElasticSearch 8.x, RU-анализатор, alias-стратегия.
- **Storage**: `local` (медиа на диске), Intervention Image/FFmpeg.
- **Auth**: cookie-based JWT (HttpOnly, Secure, SameSite=Strict), CSRF cookie + заголовок.
- **Кэш**: spatie/responsecache (гостей только), ETag/Last-Modified.
- **Ops**: простой; окружения dev/prod; без мультисайта/бэкапов (по требованию MVP).

---

## 3) Доменные сущности (обзор)

- **PostType** — реестр типов (ядро: `page`; из плагинов: `product` и т.д.).
- **Entry** — запись (заголовок, slug, статус `draft/published`, `published_at`, JSON-данные/SEO, шаблон).
- **EntrySlug** — история слугов (`is_current` + таймштамп).
- **Таксономии/термины** — `taxonomies` (`categories`, `tags`), `terms` + `term_tree` (closure table), `entry_term` (pivot).
- **Media** — файлы (минимум: `image/video/audio/docs`), варианты (thumbnails/webp/avif/…).
- **Options** — опции сайта (`site:home_entry_id` и пр.).
- **Plugins** — модульность: `plugins`, `plugin_migrations`, `plugin_reserved`.
- **ReservedRoute** — зарезервированные пути/префиксы (`admin`, `api`, префиксы плагинов).
- **Redirects** — редиректы (в т.ч. из истории слугов).
- **Audit** — аудит действий (логины, CRUD).
- **Outbox** — гарантированная доставка событий (ES-индексация/кэш).

---

## 4) URL-модель и публикация

- **Плоские URL для Page**: `/{slug}`.  
  Конфликты с ядром/плагинами предотвращаются таблицей `reserved_routes` и триггером/валидацией.
- **Slug**: авто-транслитерация `RU → lat` (например, «Страница» → `stranica`) + уникализация (`-2`, `-3`…).
- **История слугов**: при изменении slug — запись в `entry_slugs` и создание 301 в `redirects`.
- **Статусы**: `draft`/`published`. Публикация — только если `published_at <= now()`.

---

## 5) Компоненты, partials и шаблоны

- **Единая система**: partials трактуются как компоненты **без схемы**.  
  Компонент: `{ slug, view, optional schema, version }`.
- **Blocks-поле**: массив `{type, version, props}`; компоненты проверяются/мигрируются (versioned).
- **Санитайзинг**: белые списки тегов/атрибутов для richtext/props.
- **Выбор шаблона** (приоритет): `template_override > postType.template > default`.

---

## 6) Таксономии

- **Категории** (`hierarchical=1`) и **теги** (`hierarchical=0`).
- **Иерархия**: closure-table `term_tree` (ancestor/descendant/depth) — быстрые выборки, безопасные переносы веток.
- **Связь**: `entry_term` N:M.

---

## 7) Медиа-платформа

- **Типы**: изображения/видео/аудио/доки.
- **Метаданные**: MIME, размеры, EXIF, `sha256` (дедупликация), `alt`, `meta_json` (в т.ч. focal point).
- **Варианты**: `media_variants (media_id, variant_key)` → путь, размеры, размер файла.  
  Поддержка `thumb/medium/large/webp/avif`, водяной знак (часть ключа варианта).
- **Пайплайн**:
  - коррекция EXIF-ориентации;
  - генерация деривативов очередью или on-demand по **подписанному URL** (с TTL);
  - кэширование результата; хранение на `local` диске.
- **Безопасность**: запрет **hard-delete**, если медиа привязано (`RESTRICT`); отчёт «сирот».

---

## 8) Поиск (ElasticSearch)

- **Язык**: RU (нет мультиязычности, нет англ. индекса).
- **Индексы/алиасы**: `entries_vX` + алиасы `entries_read`/`entries_write` для атомарных перекидываний.
- **Маппинг**: поля `title`, агрегированный `content` из `data_json`/blocks, analyzer `russian`, lowercase/folding.
- **Индексатор**: события идут в **outbox**, воркер доставляет в ES (create/update/delete).
- **Публичный поиск**: только `published && published_at <= now()`; хайлайт; фильтры `postType/terms`.
- **Админ-поиск**: включает черновики.

---

## 9) Безопасность и доступ

- **JWT в cookie**:
  - Cookies: `cms_at` (access), `cms_rt` (refresh), оба HttpOnly + Secure + SameSite=Strict.
  - `POST /api/v1/auth/login` — логин по email/паролю, установка cookies.
  - `POST /api/v1/auth/refresh` — одноразовая ротация refresh.
  - `POST /api/v1/auth/logout` — инвалидация refresh + очистка cookies.
- **CSRF**:
  - Отдельный **CSRF cookie** `cms_csrf` и требование заголовка `X-CSRF-Token` на state-changing запросах.
- **Права**:
  - Один админ-пользователь имеет все права. `Gate::before()` возвращает allow.
- **Кэш-изоляция**:
  - ResponseCache — **только для гостей**. У авторизованных: `Vary: Cookie` и кэш отключён.
- **Логирование**:
  - Аудит (таблица `audits`): login, CRUD, кто/когда/откуда; diff (до/после) по возможности.
- 2FA, IP-ограничения, уведомления о входе — **не нужны** (MVP).

---

## 10) REST API (высокоуровневый обзор)

Префикс **`/api/v1`**, ошибки в формате **RFC 7807** (problem+json).

### Аутентификация
- `POST /auth/login` → 200 + cookies (`cms_at`, `cms_rt`).
- `POST /auth/refresh` → 200 + новый `cms_at` + ротация `cms_rt`.
- `POST /auth/logout` → 204.

### Публичное
- `GET /search?q=&postType=&terms=&sort=` — поиск (ES).
- `GET /pages/{slug}` — публичная страница (или `GET /{slug}` через веб-роут с контроллером).

### Админ (защищено `admin.auth`)
- **PostTypes**: `GET/PUT /admin/post-types/{slug}` (редактирование `options_json`/`template`).
- **Entries**: `GET /admin/entries?filters…`, `POST /admin/entries`, `PUT /admin/entries/{id}`, `DELETE (soft)` и массовые операции.
- **Taxonomies/Terms**: CRUD; привязка/отвязка терминов от записей; изменение иерархии категорий.
- **Media**: `POST /admin/media` (multipart), `GET /admin/media`, soft delete, выбор для полей записей.
- **Options**: `GET/PUT /admin/options/{namespace}/{key}` — JSON-значения.
- **Plugins**: `GET /admin/plugins`, `POST /admin/plugins/{slug}/enable|disable|sync`.
- **Search**: `POST /admin/search/reindex` — атомарный ребилд индекса.
- **Redirects** (плагин): CRUD.

---

## 11) Веб-маршрутизация (public)

- **Зарезервированные пути**: `reserved_routes(kind='path'|'prefix')` — исключаются из fallback router.
- **Admin SPA**: доступно по `/admin` (отдельный фронтенд).
- **Fallback**: роут `/{slug}` (regex) перехватывает плоские страницы, кроме зарезервированных путей/префиксов.
- **Главная**: `/` читает `site:home_entry_id` и рендерит соответствующий Entry.

---

## 12) Админка (React/TS/MobX)

- **Экраны**:
  - Вход, список Pages с фильтрами/массовыми операциями, редактор Page (title, auto-slug, blocks, media-пикер).
  - Таксономии: дерево категорий (drag-n-drop), теги.
  - Медиа-менеджер: грид/лист, превью, загрузка, удаление, выбор.
  - Опции сайта: выбор главной страницы.
  - Плагины: enable/disable/sync + статус.
  - Elastic панель: статус алиасов/доков, Reindex.
- **Микро-панель (toolbar) на сайте**:
  - Видима только при наличии auth cookie; ссылки «Редактировать»/«К списку».
  - Кэш для страниц с toolbar отключён.

---

## 13) Плагины и расширяемость

- **Дискавери**: Composer-autoload + каталог `/plugins` (манифест `plugin.json`, `PluginServiceProvider`, `routes`, `migrations`, `views`).
- **Возможности**:
  - Регистрировать маршруты/компоненты, резервы путей (`plugin_reserved`), свои таблицы.
  - Подписываться на хуки/filters ядра (actions/filters, приоритеты).
- **Плагины MVP**:
  - **Redirects** — менеджмент редиректов, интеграция с историей слугов.
  - **Blocks** — UI редактор блоков, базовые блоки (`hero`, `gallery`, `faq`), миграции props по version.
  - **Shop** — добавляет PostType `product` и свой функционал магазина.
  - **Seo** — формы мета-данных, `robots.txt`, `sitemap.xml`, og-image генератор.  
    (Визуальный превью сниппетов — **не нужно** в ядре; из плагина.)

---

## 14) Хранилище данных (MySQL 8) — ключевые таблицы

> JSON вместо EAV, частичные индексы эмулируются сгенерированными колонками/триггерами.

- **post_types** `(id, slug UNIQUE, name, template, options_json JSON, timestamps)`
- **entries**  
  `(id, post_type_id FK, title, slug, status ENUM, published_at, author_id FK, data_json JSON, seo_json JSON, template_override, version INT, deleted_at, timestamps, is_active GENERATED)`  
  **UNIQUE(post_type_id, slug, is_active)**; триггеры на уникальность slug для `page` + проверка `reserved_routes`.
- **entry_slugs** `(entry_id FK, slug, is_current, created_at)`; PK `(entry_id, slug)`.
- **reserved_routes** `(id, path UNIQUE, kind ENUM(prefix|path), source ENUM(core|plugin))`.
- **taxonomies** `(id, slug UNIQUE, name, hierarchical)`
- **terms**  
  `(id, taxonomy_id FK, slug, name, meta_json JSON, deleted_at, timestamps, is_active GENERATED)`  
  **UNIQUE(taxonomy_id, slug, is_active)`.
- **term_tree** `(ancestor_id FK, descendant_id FK, depth)`, PK `(ancestor_id, descendant_id)`.
- **entry_term** `(entry_id FK, term_id FK)`, PK `(entry_id, term_id)`.
- **media** `(id, disk, path UNIQUE, original_name, mime, size, width, height, alt, sha256, meta_json JSON, deleted_at, timestamps)`.
- **media_variants** `(media_id FK, variant_key, path UNIQUE, width, height, size)`, PK `(media_id, variant_key)`.
- **entry_media** `(entry_id FK, media_id FK, field_key)`, PK `(entry_id, media_id, field_key)`; FK на media — `RESTRICT`.
- **options** `(id, namespace, key, value_json JSON, timestamps)`, UNIQUE `(namespace, key)`.
- **plugins** `(id, slug UNIQUE, version, enabled, manifest_json JSON, timestamps)` +  
  **plugin_migrations** `(plugin_id, migration, applied_at)` +  
  **plugin_reserved** `(plugin_id, path UNIQUE, kind ENUM(prefix|path))`.
- **audits** `(id, user_id FK, action, subject_type, subject_id, diff_json JSON, ip, ua, timestamps)`.
- **outbox** `(id, topic, payload_json JSON, status ENUM(pending|sent|failed), attempts, available_at, timestamps)`.
- **redirects** `(id, from_path UNIQUE, to_path, code, hit_count, timestamps)`.

**Инварианты**:
- Уникальность slug для живых записей (soft-delete в расчёт не входит) — через `is_active` (generated).
- Уникальность `page.slug` глобально + запрет конфликтов с `reserved_routes`.
- История слугов и авто-301 при изменении slug.
- Публикация: `status='published'` ⇒ `published_at <= now()`.

---

## 15) Производительность и кэш

- **Индексы**: `(status, published_at)` на `entries`, индексы по slug/JSON-полям (через виртуальные колонки), по таксономиям/term_tree.
- **Кэш**: ResponseCache c тегами (`entry:{id}`, `postType:{slug}`, `term:{id}`), инвалидация при изменениях.
- **ETag/Last-Modified**, 304; gzip/brotli (на уровне nginx).
- **Toolbar** отключает кэш страниц для авторизованных.

---

## 16) Качество и тестирование

- **Unit/Feature**:
  - Модели/связи (Entry↔PostType/Terms/Media/Slugs), скоупы `published/ofType`, URL-хелпер.
  - Смена slug → запись в `entry_slugs` + создание `redirect`.
  - Media: генерация деривативов, защита удаления, дедуп по sha256.
  - Search: индексатор (outbox→ES), публичный/админский фильтры.
  - Auth: login/refresh/logout, CSRF.
- **E2E смоук**:
  - логин → создать Page → добавить медиа → добавить blocks → publish → фронт → поиск → смена slug → 301.

---

## 17) Безопасность (детализация)

- **Cookies**: `HttpOnly`, `Secure`, `SameSite=Strict`, различные имена (`cms_at`, `cms_rt`, `cms_csrf`).
- **CSRF**: отдельный cookie, заголовок `X-CSRF-Token`.
- **CORS**: если админка на отдельном origin — CORS только для SPA; preflight; куки с `credentials`.
- **Валидация**: строгая по slug и reserved routes; санитайзинг HTML/props; ограничение MIME на загрузке.
- **Аудит**: запись ключевых действий (`audits`).
- **Роли/ACL**: один админ, `Gate::before()` открывает все разрешения.

---

## 18) Плагины (модель взаимодействия)

- **Манифест**: `plugin.json` (`slug`, `name`, `version`, `requires/conflicts`, `routes`, `migrations`, `views`).
- **Провайдер**: `PluginServiceProvider` регистрирует хуки, роуты, view-namespace, компоненты, резервирует пути.
- **Команда**: `cms:plugins:sync` — сканирует `/plugins`, применяет миграции, обновляет `plugins`/`plugin_migrations`/`plugin_reserved`, чистит кэш.
- **Хуки**:
  - **actions** — события (до/после save, publish, delete, media-upload, reindex).
  - **filters** — модификация payload (например, расширение SEO или Blocks).

---

## 19) Фронтенд (public) — Blade/гибрид

- **Layouts**: базовый `layouts/app.blade.php`.
- **Partials**: `header`, `footer` (без схемы).
- **Components/Blocks**: Blade-вью по `type`, graceful-fallback (неизвестный тип пропускается).
- **Главная**: View читает `site:home_entry_id`, рендерит Entry.
- **SEO**: подключение метаданных (из плагина Seo) в `<head>`.

---

## 20) Ограничения и известные решения

- **MySQL 8** не поддерживает partial UNIQUE ⇒ используем сгенерированные колонки (`is_active`) и триггеры на `page.slug`.
- **EAV-ад** избегается JSON-полями + точечными индексами/виртуальными колонками.
- **ES**: все записи проходят через **outbox** (exactly-once на уровне «по крайней мере один раз» + идемпотентность индексатора).
- **Redirects**: история слугов → auto-301; ручные — через плагин.

---

## 21) Что входит в MVP

- Ядро (типы/записи/таксономии/медиа/опции/плагины/редиректы/аудит/аутбокс/резерв роутов).
- Админка SPA с экранами Pages, таксономий, медиа, опций, плагинов, поиска.
- Плагины: **Blocks**, **Seo**, **Redirects**.  
  **Shop** — посттип `product` и минимальный storefront (опционально подключается).
- Поиск в ES (RU), публичный и админский.
- Минимум опсов (dev/prod), без мультисайта/бэкапов (при необходимости добавляется позже).

---

## 22) Приложение: соответствие требованиям

- Кастомные типы записей ✔
- Кастомные поля (сложная логика через JSON + компоненты) ✔
- Библиотека медиафайлов + пайплайн (thumbnails/webp/avif/watermark) ✔
- Таксономии (категории/теги) ✔
- Система плагинов + автодискавери ✔
- Partials = Components (без явных полей) ✔
- SEO (через плагин: meta, robots/sitemap, og-image) ✔
- Cookie-based JWT + CSRF cookie ✔
- Elastic поиск (RU), алиасы, ребилд ✔
- Плагины могут предоставлять произвольный функционал ✔
- Шаблоны для типов записей, назначаемая главная ✔
- Массовые операции в админке ✔
- Админка: React+TS+MobX ✔
- REST API + middleware ✔
- Один админ-пользователь ✔
- Страницы опций ✔
- Плоские URL `/slug`, резервация коллизий ✔
- Транслит RU→lat с уникализацией ✔
- Статусы `draft/published` + `published_at` ✔
- Микро-панель на фронте при входе администратора ✔
- Хранилище медиа: local ✔
- Логи входов/действий (Audit); 2FA/IP ограничение — **не нужно** ✔
