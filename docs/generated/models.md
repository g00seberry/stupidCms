# Models

## Audit
**ID:** `model:App\Models\Audit`
**Path:** `app/Models/Audit.php`

Eloquent модель для аудита изменений (Audit).

### Details
Хранит историю изменений сущностей системы для аудита и отслеживания действий пользователей.

### Meta
- **Table:** `audits`
- **Casts:** `diff_json` => `array`
- **Relations:**
  - `user`: belongsTo → `App\Models\User`

### Tags
`audit`


---

## Blueprint
**ID:** `model:App\Models\Blueprint`
**Path:** `app/Models/Blueprint.php`

Шаблон структуры данных для Entry.

### Meta
- **Table:** `blueprints`
- **Fillable:** `name`, `code`, `description`
- **Guarded:** `*`
- **Relations:**
  - `paths`: hasMany → `App\Models\Path`
  - `embeds`: hasMany → `App\Models\BlueprintEmbed`
  - `embeddedIn`: hasMany → `App\Models\BlueprintEmbed`
  - `postTypes`: hasMany → `App\Models\PostType`

### Tags
`blueprint`


---

## BlueprintEmbed
**ID:** `model:App\Models\BlueprintEmbed`
**Path:** `app/Models/BlueprintEmbed.php`

Связь встраивания blueprint'а.

### Meta
- **Table:** `blueprint_embeds`
- **Fillable:** `blueprint_id`, `embedded_blueprint_id`, `host_path_id`
- **Guarded:** `*`
- **Relations:**
  - `blueprint`: belongsTo → `App\Models\Blueprint`
  - `embeddedBlueprint`: belongsTo → `App\Models\Blueprint`
  - `hostPath`: belongsTo → `App\Models\Path`

### Tags
`blueprintembed`


---

## DocRef
**ID:** `model:App\Models\DocRef`
**Path:** `app/Models/DocRef.php`

Индексированная ссылка на другой Entry.

### Meta
- **Table:** `doc_refs`
- **Fillable:** `entry_id`, `path_id`, `array_index`, `target_entry_id`
- **Guarded:** `*`
- **Relations:**
  - `entry`: belongsTo → `App\Models\Entry`
  - `path`: belongsTo → `App\Models\Path`
  - `targetEntry`: belongsTo → `App\Models\Entry`

### Tags
`docref`


---

## DocValue
**ID:** `model:App\Models\DocValue`
**Path:** `app/Models/DocValue.php`

Индексированное скалярное значение из Entry.data_json.

### Details
Использует уникальный индекс (entry_id, path_id, array_index) для обеспечения уникальности.
array_index может быть NULL для cardinality=one, поэтому используется уникальный индекс вместо первичного ключа.

### Meta
- **Table:** `doc_values`
- **Fillable:** `entry_id`, `path_id`, `array_index`, `value_string`, `value_int`, `value_float`, `value_bool`, `value_datetime`, `value_text`, `value_json`
- **Guarded:** `*`
- **Casts:** `value_bool` => `boolean`, `value_json` => `array`, `value_datetime` => `datetime`
- **Relations:**
  - `entry`: belongsTo → `App\Models\Entry`
  - `path`: belongsTo → `App\Models\Path`

### Tags
`docvalue`


---

## Entry
**ID:** `model:App\Models\Entry`
**Path:** `app/Models/Entry.php`

Eloquent модель для записей контента (Entry).

### Details
Представляет единицу контента в CMS: статьи, страницы, посты и т.д.
Поддерживает мягкое удаление, публикацию по расписанию, связи с термами.

### Meta
- **Table:** `entries`
- **Casts:** `data_json` => `array`, `seo_json` => `array`, `published_at` => `datetime`
- **Relations:**
  - `postType`: belongsTo → `App\Models\PostType`
  - `author`: belongsTo → `App\Models\User`
  - `terms`: belongsToMany → `App\Models\Term`
  - `docValues`: hasMany → `App\Models\DocValue`
  - `docRefs`: hasMany → `App\Models\DocRef`
  - `docRefsIncoming`: hasMany → `App\Models\DocRef`
- **Factory:** `Database\Factories\EntryFactory`

### Tags
`entry`


---

## FormConfig
**ID:** `model:App\Models\FormConfig`
**Path:** `app/Models/FormConfig.php`

Eloquent модель для конфигурации формы компонентов (FormConfig).

### Details
Хранит конфигурацию формы компонентов для конкретной пары PostType (slug) + Blueprint.
Конфигурация представляет собой JSON объект, где ключи - это full_path из Path,
а значения - EditComponent (конфигурация компонента редактирования).

### Meta
- **Table:** `form_configs`
- **Fillable:** `post_type_slug`, `blueprint_id`, `config_json`
- **Guarded:** `*`
- **Casts:** `config_json` => `array`
- **Relations:**
  - `blueprint`: belongsTo → `App\Models\Blueprint`
- **Factory:** `Database\Factories\FormConfigFactory`

### Tags
`formconfig`


---

## Media
**ID:** `model:App\Models\Media`
**Path:** `app/Models/Media.php`

Eloquent модель для медиа-файлов (Media).

### Details
Представляет загруженные файлы: изображения, видео, аудио, документы.
Использует ULID в качестве первичного ключа. Поддерживает мягкое удаление.
Уникальность обеспечивается по комбинации (disk, path).
Специфичные метаданные хранятся в связанных таблицах:
- MediaImage для изображений (width, height, exif_json)
- MediaAvMetadata для видео/аудио (duration_ms, bitrate, codecs и т.д.)

### Meta
- **Table:** `media`
- **Casts:** `deleted_at` => `datetime`, `size_bytes` => `integer`
- **Relations:**
  - `variants`: hasMany → `App\Models\MediaVariant`
  - `image`: hasOne → `App\Models\MediaImage`
  - `avMetadata`: hasOne → `App\Models\MediaAvMetadata`

### Tags
`media`


---

## MediaAvMetadata
**ID:** `model:App\Models\MediaAvMetadata`
**Path:** `app/Models/MediaAvMetadata.php`

Eloquent модель для нормализованных AV-метаданных медиа (MediaAvMetadata).

### Details
Хранит технические характеристики аудио/видео:
длительность, битрейт, частоту кадров, количество кадров и кодеки.

### Meta
- **Table:** `media_av_metadata`
- **Casts:** `duration_ms` => `integer`, `bitrate_kbps` => `integer`, `frame_rate` => `float`, `frame_count` => `integer`
- **Relations:**
  - `media`: belongsTo → `App\Models\Media`

### Tags
`mediaavmetadata`


---

## MediaImage
**ID:** `model:App\Models\MediaImage`
**Path:** `app/Models/MediaImage.php`

Eloquent модель для метаданных изображений (MediaImage).

### Details
Хранит специфичные метаданные для изображений:
размеры (width, height) и EXIF метаданные.
Связана с Media через отношение один-к-одному.

### Meta
- **Table:** `media_images`
- **Casts:** `width` => `integer`, `height` => `integer`, `exif_json` => `array`
- **Relations:**
  - `media`: belongsTo → `App\Models\Media`

### Tags
`mediaimage`


---

## MediaVariant
**ID:** `model:App\Models\MediaVariant`
**Path:** `app/Models/MediaVariant.php`

Eloquent модель для вариантов медиа-файлов (MediaVariant).

### Details
Представляет производные версии медиа-файла: превью, миниатюры, ресайзы изображений.
Использует ULID в качестве первичного ключа.

### Meta
- **Table:** `media_variants`
- **Casts:** `status` => `App\Domain\Media\MediaVariantStatus`, `started_at` => `immutable_datetime`, `finished_at` => `immutable_datetime`
- **Relations:**
  - `media`: belongsTo → `App\Models\Media`

### Tags
`mediavariant`


---

## Option
**ID:** `model:App\Models\Option`
**Path:** `app/Models/Option.php`

Eloquent модель для опций системы (Option).

### Details
Хранит настройки системы в формате ключ-значение с поддержкой пространств имён.
Использует ULID в качестве первичного ключа. Поддерживает мягкое удаление.

### Meta
- **Table:** `options`
- **Fillable:** `namespace`, `key`, `value_json`, `description`
- **Guarded:** `*`
- **Casts:** `value_json` => `App\Casts\AsJsonValue`

### Tags
`option`


---

## Outbox
**ID:** `model:App\Models\Outbox`
**Path:** `app/Models/Outbox.php`

Eloquent модель для исходящих сообщений (Outbox).

### Details
Хранит задачи/сообщения для асинхронной обработки с поддержкой повторных попыток.
Используется для реализации паттерна Outbox для гарантированной доставки.

### Meta
- **Table:** `outbox`
- **Casts:** `payload_json` => `array`, `attempts` => `integer`, `available_at` => `datetime`

### Tags
`outbox`


---

## Path
**ID:** `model:App\Models\Path`
**Path:** `app/Models/Path.php`

Поле внутри blueprint с материализованным full_path.

### Meta
- **Table:** `paths`
- **Fillable:** `blueprint_id`, `parent_id`, `name`, `data_type`, `cardinality`, `is_required`, `is_indexed`, `sort_order`, `validation_rules`
- **Guarded:** `source_blueprint_id`, `blueprint_embed_id`, `is_readonly`, `full_path`
- **Casts:** `is_required` => `boolean`, `is_indexed` => `boolean`, `is_readonly` => `boolean`, `validation_rules` => `array`
- **Relations:**
  - `blueprint`: belongsTo → `App\Models\Blueprint`
  - `sourceBlueprint`: belongsTo → `App\Models\Blueprint`
  - `blueprintEmbed`: belongsTo → `App\Models\BlueprintEmbed`
  - `parent`: belongsTo → `App\Models\Path`
  - `children`: hasMany → `App\Models\Path`

### Tags
`path`


---

## Plugin
**ID:** `model:App\Models\Plugin`
**Path:** `app/Models/Plugin.php`

Eloquent модель для плагинов (Plugin).

### Details
Представляет плагины системы с информацией о состоянии, метаданных и синхронизации.
Использует ULID в качестве первичного ключа.

### Meta
- **Table:** `plugins`
- **Casts:** `enabled` => `boolean`, `meta_json` => `array`, `last_synced_at` => `immutable_datetime`

### Tags
`plugin`


---

## PostType
**ID:** `model:App\Models\PostType`
**Path:** `app/Models/PostType.php`

Eloquent модель для типов записей (PostType).

### Details
Определяет типы контента в CMS (например, 'article', 'page', 'post').
Каждый тип может иметь свои опции и настройки.

### Meta
- **Table:** `post_types`
- **Fillable:** `slug`, `name`, `options_json`, `blueprint_id`
- **Guarded:** `*`
- **Casts:** `options_json` => `App\Casts\AsPostTypeOptions`
- **Relations:**
  - `entries`: hasMany → `App\Models\Entry`
  - `blueprint`: belongsTo → `App\Models\Blueprint`
- **Factory:** `Database\Factories\PostTypeFactory`

### Tags
`posttype`


---

## Redirect
**ID:** `model:App\Models\Redirect`
**Path:** `app/Models/Redirect.php`

Eloquent модель для редиректов (Redirect).

### Details
Хранит правила перенаправления URL (301, 302 и т.д.).

### Meta
- **Table:** `redirects`

### Tags
`redirect`


---

## RefreshToken
**ID:** `model:App\Models\RefreshToken`
**Path:** `app/Models/RefreshToken.php`

Eloquent модель для JWT refresh токенов (RefreshToken).

### Details
Отслеживает refresh токены для обновления access токенов.
Поддерживает ротацию токенов через parent_jti и отслеживание использования/отзыва.

### Meta
- **Table:** `refresh_tokens`
- **Fillable:** `user_id`, `jti`, `expires_at`, `used_at`, `revoked_at`, `parent_jti`
- **Guarded:** `*`
- **Casts:** `expires_at` => `datetime`, `used_at` => `datetime`, `revoked_at` => `datetime`
- **Relations:**
  - `user`: belongsTo → `App\Models\User`

### Tags
`refreshtoken`


---

## ReservedRoute
**ID:** `model:App\Models\ReservedRoute`
**Path:** `app/Models/ReservedRoute.php`

Eloquent модель для зарезервированных путей (ReservedRoute).

### Details
Хранит пути, которые зарезервированы системой и не могут использоваться
для записей контента. Поддерживает два типа: 'path' (точное совпадение)
и 'prefix' (префикс пути).

### Meta
- **Table:** `reserved_routes`
- **Fillable:** `path`, `kind`, `source`
- **Guarded:** `*`
- **Casts:** `created_at` => `datetime`, `updated_at` => `datetime`

### Tags
`reservedroute`


---

## Taxonomy
**ID:** `model:App\Models\Taxonomy`
**Path:** `app/Models/Taxonomy.php`

Eloquent модель для таксономий (Taxonomy).

### Details
Определяет группы термов: категории, теги, метки и т.д.
Может быть иерархической (hierarchical = true) или плоской (hierarchical = false).

### Meta
- **Table:** `taxonomies`
- **Casts:** `options_json` => `array`, `hierarchical` => `boolean`
- **Relations:**
  - `terms`: hasMany → `App\Models\Term`
- **Factory:** `Database\Factories\TaxonomyFactory`

### Tags
`taxonomy`


---

## Term
**ID:** `model:App\Models\Term`
**Path:** `app/Models/Term.php`

Eloquent модель для термов (Term).

### Details
Представляет элементы таксономии: категории, теги, метки и т.д.
Поддерживает иерархическую структуру через closure-table (term_tree).
Поддерживает мягкое удаление.

### Meta
- **Table:** `terms`
- **Casts:** `meta_json` => `array`
- **Relations:**
  - `taxonomy`: belongsTo → `App\Models\Taxonomy`
  - `entries`: belongsToMany → `App\Models\Entry`
  - `ancestors`: belongsToMany → `App\Models\Term`
  - `descendants`: belongsToMany → `App\Models\Term`
  - `parent`: belongsToMany → `App\Models\Term`
  - `children`: belongsToMany → `App\Models\Term`
- **Factory:** `Database\Factories\TermFactory`

### Tags
`term`


---

## TermTree
**ID:** `model:App\Models\TermTree`
**Path:** `app/Models/TermTree.php`

Eloquent модель для closure-table иерархии термов (TermTree).

### Details
Реализует closure-table паттерн для хранения иерархических связей между термами.
Позволяет эффективно получать всех предков и потомков терма.

### Meta
- **Table:** `term_tree`

### Tags
`termtree`


---

## User
**ID:** `model:App\Models\User`
**Path:** `app/Models/User.php`

Eloquent модель для пользователей (User).

### Details
Представляет пользователей системы с поддержкой аутентификации и авторизации.
Поддерживает административные права и разрешения.

### Meta
- **Table:** `users`
- **Fillable:** `name`, `email`, `password`, `email_verified_at`
- **Guarded:** `is_admin`
- **Relations:**
  - `entries`: hasMany → `App\Models\Entry`
  - `refreshTokens`: hasMany → `App\Models\RefreshToken`
  - `notifications`: morphMany → `App\Models\DatabaseNotification`

### Tags
`user`


---
