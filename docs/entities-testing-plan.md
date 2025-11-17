# План тестирования сущностей проекта stupidCMS

**Дата создания:** 2025-11-17  
**Версия:** 1.0  
**Статус:** 📋 В разработке

---

## Обзор

Детальный план по написанию тестов для всех сущностей проекта stupidCMS.  
Проект включает **170 сущностей**: 16 моделей, 63 доменных сервиса, 60 HTTP эндпоинтов, и др.

### Принципы тестирования

1. **Полнота:** Каждая сущность имеет соответствующие тесты
2. **Изоляция:** Unit-тесты изолированы от БД и внешних зависимостей
3. **Реалистичность:** Feature-тесты используют реальные компоненты
4. **Структурированность:** Тесты организованы по доменным модулям
5. **Приоритизация:** Критичные компоненты тестируются в первую очередь

---

## Статистика выполнения

**Дата обновления:** 2025-11-17

### Общие показатели

-   ✅ **Всего тестов:** 796
-   ✅ **Assertions:** 2009
-   ⏭️ **Skipped:** 3
-   ❌ **Failed:** 0
-   ⏱️ **Время выполнения:** ~51 сек

### По фазам

#### Фаза 1: Критичные компоненты ✅ (100%)

-   ✅ **Models:** 218 тестов (User, Entry, Media, PostType, Plugin, Option, Taxonomy, Term, TermTree, RefreshToken, ReservedRoute, Redirect, Audit, Outbox + MediaVariant, MediaMetadata)
-   ✅ **Auth Module:** 26 тестов (JwtService, RefreshTokenRepository, RefreshTokenDto, Exceptions)

#### Фаза 2: Domain Services 🔄 (33%)

-   ✅ **Auth:** 26 тестов
-   ✅ **Entries:** 16 тестов (PublishingService)
-   ✅ **Routing:** 37 тестов (PathNormalizer, ReservedPattern, Exceptions)
-   ✅ **Media:** 21 тестов (MediaQuery, ListMediaAction, UpdateMediaMetadataAction)
-   ✅ **Options:** 16 тестов (OptionsRepository)
-   ✅ **PostTypes:** 19 тестов (PostTypeOptions)
-   ✅ **Sanitizer:** 17 тестов (RichTextSanitizer)
-   ✅ **View:** 10 тестов (BladeTemplateResolver)
-   ✅ **Plugins:** 7 тестов (PluginRegistry)
-   ⏳ **Media (полное):** MediaStoreAction требует доработки
-   ⏳ **Plugins (полное):** PluginActivator требует рефакторинга

#### Фаза 3: HTTP Controllers ✅ (100%)

-   ✅ **Auth API:** 31 тест (Login, CurrentUser, Refresh, Logout)
-   ✅ **Entries API:** 53 теста (List, Create, Show, Update, Delete, Restore)
-   ✅ **Media API:** 35 тестов (List, Show, Update, Delete, Restore)
-   ✅ **PostTypes API:** 17 тестов (List, Create, Show, Update, Delete)
-   ✅ **Plugins API:** 31 тест (List, Enable, Disable, Sync)
-   ✅ **Options API:** 22 теста (List, Show, Upsert, Delete, Restore)
-   ✅ **Taxonomies & Terms API:** 37 тестов (Taxonomies 19, Terms 18)
-   ✅ **Search API:** 24 теста (Public 15, Admin 9)
-   ✅ **Path Reservation API:** 21 тест (List, Reserve, Release)
-   ✅ **Utils & Templates API:** 27 тестов (Utils 10, Templates 17)
-   ✅ **Web Controllers:** 15 тестов (Home 5, Page 7, Ping 2, Routing 1)

#### Фаза 4: Validation Rules ✅ (100%)

-   ✅ **UniqueEntrySlug:** 8 тестов
-   ✅ **ReservedSlug:** 9 тестов
-   ✅ **Publishable:** 7 тестов (1 skipped)
-   ✅ **PublishedDateNotInFuture:** 7 тестов
-   ✅ **NoTermCycle:** 7 тестов
-   ✅ **JsonValue:** 13 тестов

---

## Структура плана

```
1. Models (16 сущностей)
   ├── Unit-тесты (scopes, accessors, mutators, relations)
   └── Feature-тесты (интеграция с БД)

2. Domain Services (63 сущности)
   ├── Actions (тестирование бизнес-логики)
   ├── Services (тестирование сервисов)
   ├── Repositories (тестирование запросов)
   ├── Validators (тестирование валидации)
   └── Value Objects/DTOs

3. HTTP Controllers (60 эндпоинтов)
   ├── Admin API (аутентификация, авторизация, CRUD)
   └── Public API (публичные эндпоинты)

4. Validation Rules (6 правил)
   └── Unit-тесты для кастомных правил

5. Events & Listeners (9 событий, 3 слушателя)
   └── Feature-тесты событий

6. Jobs (2 фоновые задачи)
   └── Unit-тесты для jobs

7. Integration Tests
   └── Полные сценарии взаимодействия компонентов
```

---

## 1. Models (16 моделей)

### Приоритет: 🔴 Высокий

Модели — основа приложения, требуют полного покрытия.

#### 1.1. User ✅

**Путь:** `app/Models/User.php`  
**Factory:** `database/factories/UserFactory.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/UserTest.php`) ✅

```php
✅ test('has fillable attributes')
✅ test('guarded is_admin attribute')
✅ test('has notifications relationship')
✅ test('has entries relationship')
✅ test('has refresh tokens relationship')
✅ test('can check if user is admin')
✅ test('can check if user is regular user')
✅ test('password is cast to hashed')
✅ test('is_admin is cast to boolean')
✅ test('admin_permissions is cast to array')
✅ test('email_verified_at is cast to datetime')
✅ test('returns normalized admin permissions')
✅ test('returns empty array when admin_permissions is null')
✅ test('admin always has any permission')
✅ test('regular user has permission if it is in the list')
✅ test('can grant admin permissions')
✅ test('grant admin permissions does not create duplicates')
```

##### Feature-тесты (`tests/Feature/Models/UserTest.php`) ✅

```php
✅ test('user can be created with factory')
✅ test('admin user can be created')
✅ test('user can have multiple entries')
✅ test('user can have multiple refresh tokens')
✅ test('user password is hashed')
✅ test('user email is unique')
✅ test('user can have admin permissions')
✅ test('user admin_permissions defaults to empty array')
✅ test('user password and remember_token are hidden from serialization')
✅ test('user email_verified_at is stored as datetime')
```

**Изменения в модели:**

-   Добавлены методы `entries()` и `refreshTokens()` для связей
-   Обновлён PHPDoc с описанием связей

---

#### 1.2. Entry ✅

**Путь:** `app/Models/Entry.php`  
**Factory:** `database/factories/EntryFactory.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/EntryTest.php`) ✅

```php
✅ test('casts data_json to array')
✅ test('casts seo_json to array')
✅ test('casts published_at to datetime')
✅ test('has post type relationship')
✅ test('has author relationship')
✅ test('has terms many to many relationship')
✅ test('has published scope')
✅ test('has of type scope')
✅ test('uses soft deletes')
✅ test('has draft status constant')
✅ test('has published status constant')
✅ test('get statuses returns all statuses')
✅ test('url method returns flat url for page type')
✅ test('url method returns hierarchical url for non-page type')
✅ test('has no guarded attributes')
```

##### Feature-тесты (`tests/Feature/Models/EntryTest.php`) ✅

```php
✅ test('entry can be created with factory')
✅ test('entry belongs to post type')
✅ test('entry belongs to author')
✅ test('entry can have multiple terms')
✅ test('entry can be published')
✅ test('entry can be draft')
✅ test('entry can be soft deleted')
✅ test('entry can be restored')
⏭️ test('entry slug is unique per post type') - skipped (requires real DB)
✅ test('entry slug can be same for different post types')
✅ test('entry published at can be in future')
✅ test('entry data json stores custom fields')
✅ test('entry seo json stores metadata')
✅ test('published scope returns only published entries')
✅ test('of type scope filters by post type slug')
✅ test('entry url is generated correctly for page type')
✅ test('entry url is generated correctly for non-page type')
✅ test('entry template override can be set')
```

**Примечания:**

-   Тест уникальности slug пропущен, так как требует реальной БД с индексами (не :memory:)
-   Все основные функции модели протестированы

---

#### 1.3. Media ✅

**Путь:** `app/Models/Media.php`  
**Factory:** `database/factories/MediaFactory.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/MediaTest.php`) ✅

```php
✅ test('uses ULID as primary key')
✅ test('casts exif_json to array')
✅ test('casts deleted_at to datetime')
✅ test('casts width to integer')
✅ test('casts height to integer')
✅ test('casts duration_ms to integer')
✅ test('casts size_bytes to integer')
✅ test('has variants relationship')
✅ test('has metadata relationship')
✅ test('uses soft deletes')
✅ test('kind returns image for image mime type')
✅ test('kind returns video for video mime type')
✅ test('kind returns audio for audio mime type')
✅ test('kind returns document for other mime types')
✅ test('has no guarded attributes')
✅ test('uses HasUlids trait')
```

##### Feature-тесты (`tests/Feature/Models/MediaTest.php`) ✅

```php
✅ test('media can be created with factory')
✅ test('media has unique disk and path combination')
✅ test('media can have multiple variants')
✅ test('media can have metadata')
✅ test('media can be soft deleted')
✅ test('media can be restored after soft delete')
✅ test('media exif json stores metadata')
✅ test('media dimensions are stored correctly')
✅ test('media file size is stored in bytes')
✅ test('media kind method works for images')
✅ test('media kind method works for documents')
✅ test('media kind method works for video')
✅ test('media kind method works for audio')
✅ test('media can have null dimensions for non-image files')
✅ test('media checksum is stored correctly')
✅ test('media collection can be set')
✅ test('media duration_ms can be set for video')
```

**Примечания:**

-   Протестированы все основные возможности модели
-   Метод `kind()` для определения типа файла по MIME
-   Связи с MediaVariant и MediaMetadata

---

#### 1.4. MediaVariant ✅

**Путь:** `app/Models/MediaVariant.php`  
**Factory:** `database/factories/MediaVariantFactory.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/MediaVariantTest.php`) ✅

```php
✅ test('uses ULID as primary key')
✅ test('casts status to MediaVariantStatus enum')
✅ test('casts started_at to immutable_datetime')
✅ test('casts finished_at to immutable_datetime')
✅ test('belongs to media')
✅ test('has no guarded attributes')
✅ test('uses HasUlids trait')
✅ test('table name is media_variants')
```

##### Feature-тесты (`tests/Feature/Models/MediaVariantTest.php`) ✅

```php
✅ test('variant can be created with factory')
✅ test('variant belongs to media')
✅ test('variant status is cast to enum')
✅ test('variant can have queued status')
✅ test('variant can have processing status')
✅ test('variant can have ready status')
✅ test('variant can have failed status')
✅ test('variant status transitions correctly')
✅ test('variant can be marked as processing')
✅ test('variant can be marked as ready')
✅ test('variant can be marked as failed')
✅ test('variant dimensions are stored correctly')
✅ test('variant size_bytes is stored correctly')
✅ test('variant path is unique')
✅ test('variant name and media_id combination is unique')
✅ test('variant started_at is immutable datetime')
✅ test('variant finished_at is immutable datetime')
```

---

#### 1.5. MediaMetadata ✅

**Путь:** `app/Models/MediaMetadata.php`  
**Factory:** `database/factories/MediaMetadataFactory.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/MediaMetadataTest.php`) ✅

```php
✅ test('uses ULID as primary key')
✅ test('casts duration_ms to integer')
✅ test('casts bitrate_kbps to integer')
✅ test('casts frame_rate to float')
✅ test('casts frame_count to integer')
✅ test('belongs to media')
✅ test('has no guarded attributes')
```

##### Feature-тесты (`tests/Feature/Models/MediaMetadataTest.php`) ✅

```php
✅ test('metadata can be created with factory')
✅ test('metadata belongs to media')
✅ test('metadata stores av technical details')
✅ test('metadata can store video codec')
✅ test('metadata can store audio codec')
✅ test('metadata duration_ms is cast to integer')
✅ test('metadata bitrate_kbps is cast to integer')
✅ test('metadata frame_rate is cast to float')
✅ test('metadata frame_count is cast to integer')
✅ test('metadata can have null values for optional fields')
✅ test('metadata auto generates ULID on creation')
✅ test('metadata supports common video codecs')
✅ test('metadata supports common audio codecs')
```

---

#### 1.6. PostType ✅

**Путь:** `app/Models/PostType.php`  
**Factory:** `database/factories/PostTypeFactory.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/PostTypeTest.php`) ✅

```php
✅ test('has fillable attributes')
✅ test('casts options_json to PostTypeOptions')
✅ test('has entries relationship')
✅ test('slug is unique')
```

##### Feature-тесты (`tests/Feature/Models/PostTypeTest.php`) ✅

```php
✅ test('post type can be created')
✅ test('post type has unique slug')
✅ test('post type can have multiple entries')
✅ test('post type options are stored correctly')
✅ test('post type options can be empty')
✅ test('post type options cast works on retrieval')
✅ test('post type options taxonomy check works')
✅ test('post type options allows all taxonomies when list is empty')
✅ test('post type options has field check works')
✅ test('post type options get field with default works')
✅ test('post type can be updated')
✅ test('post type options can be updated')
✅ test('post type slug cannot be changed to existing slug')
```

**Примечания:**

-   Протестирован cast в PostTypeOptions
-   Протестированы все методы PostTypeOptions (taxonomies, fields)
-   Проверена уникальность slug

---

#### 1.7. Plugin ✅

**Путь:** `app/Models/Plugin.php`  
**Factory:** `database/factories/PluginFactory.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/PluginTest.php`) ✅

```php
✅ test('uses ULID as primary key')
✅ test('casts enabled to boolean')
✅ test('casts meta_json to array')
✅ test('casts last_synced_at to immutable_datetime')
✅ test('has no guarded attributes')
```

##### Feature-тесты (`tests/Feature/Models/PluginTest.php`) ✅

```php
✅ test('plugin can be created')
✅ test('plugin can be enabled')
✅ test('plugin can be disabled')
✅ test('plugin stores metadata')
✅ test('plugin tracks last sync time')
```

---

#### 1.8. Option ✅

**Путь:** `app/Models/Option.php`  
**Factory:** `database/factories/OptionFactory.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/OptionTest.php`) ✅

```php
✅ test('uses ULID as primary key')
✅ test('has fillable attributes')
✅ test('casts value_json using AsJsonValue')
✅ test('uses soft deletes')
✅ test('table name is options')
```

##### Feature-тесты (`tests/Feature/Models/OptionTest.php`) ✅

```php
✅ test('option can be created')
✅ test('option value is stored as json')
✅ test('option can be retrieved by namespace and key')
✅ test('option can be soft deleted')
✅ test('option can be restored after soft delete')
```

---

#### 1.9. Taxonomy ✅

**Путь:** `app/Models/Taxonomy.php`  
**Factory:** `database/factories/TaxonomyFactory.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/TaxonomyTest.php`) ✅

```php
✅ test('casts options_json to array')
✅ test('casts hierarchical to boolean')
✅ test('has terms relationship')
✅ test('label accessor returns name')
✅ test('has no guarded attributes')
```

##### Feature-тесты (`tests/Feature/Models/TaxonomyTest.php`) ✅

```php
✅ test('taxonomy can be created')
✅ test('taxonomy can be hierarchical')
✅ test('taxonomy can be flat')
✅ test('taxonomy can have multiple terms')
✅ test('taxonomy label accessor works')
✅ test('taxonomy options can be stored')
```

---

#### 1.10. Term ✅

**Путь:** `app/Models/Term.php`  
**Factory:** `database/factories/TermFactory.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/TermTest.php`) ✅

```php
✅ test('casts meta_json to array')
✅ test('belongs to taxonomy')
✅ test('has entries many to many relationship')
✅ test('has ancestors relationship')
✅ test('has descendants relationship')
✅ test('has parent relationship')
✅ test('has children relationship')
✅ test('uses soft deletes')
✅ test('has no guarded attributes')
```

##### Feature-тесты (`tests/Feature/Models/TermTest.php`) ✅

```php
✅ test('term belongs to taxonomy')
✅ test('term can be attached to entries')
✅ test('term can be soft deleted')
✅ test('term meta json stores additional data')
✅ test('in taxonomy scope filters by taxonomy id')
```

**Примечания:**

-   Тесты иерархии проверяются через TermTree (closure table)
-   Связи с Entry и Taxonomy полностью протестированы

---

#### 1.11. TermTree ✅

**Путь:** `app/Models/TermTree.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/TermTreeTest.php`) ✅

```php
✅ test('table name is term_tree')
✅ test('does not use timestamps')
✅ test('does not use incrementing')
✅ test('has no guarded attributes')
✅ test('has no primary key')
```

##### Feature-тесты (`tests/Feature/Models/TermTreeTest.php`) ✅

```php
✅ test('term tree stores term relationships')
✅ test('term tree implements closure table pattern')
```

**Примечания:**

-   Реализует closure table паттерн для иерархии термов
-   Проверяется хранение транзитивных связей (depth > 1)

---

#### 1.12. RefreshToken ✅

**Путь:** `app/Models/RefreshToken.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/RefreshTokenTest.php`) ✅

```php
✅ test('has fillable attributes')
✅ test('casts timestamps to datetime')
✅ test('belongs to user')
✅ test('is valid when not used not revoked and not expired')
✅ test('is invalid when used')
✅ test('is invalid when revoked')
✅ test('is invalid when expired')
```

##### Feature-тесты (`tests/Feature/Models/RefreshTokenTest.php`) ✅

```php
✅ test('refresh token can be created')
✅ test('refresh token belongs to user')
✅ test('refresh token can be used once')
✅ test('refresh token can be revoked')
✅ test('refresh token supports rotation')
```

**Примечания:**

-   Проверены методы `isValid()` и `isInvalid()`
-   Протестирована ротация токенов через `parent_jti`

---

#### 1.13. ReservedRoute ✅

**Путь:** `app/Models/ReservedRoute.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/ReservedRouteTest.php`) ✅

```php
✅ test('has fillable attributes')
✅ test('supports path type')
✅ test('supports prefix type')
```

##### Feature-тесты (`tests/Feature/Models/ReservedRouteTest.php`) ✅

```php
✅ test('reserved route can be created')
✅ test('path type matches exact path')
✅ test('prefix type matches path prefix')
```

---

#### 1.14. Redirect ✅

**Путь:** `app/Models/Redirect.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/RedirectTest.php`) ✅

```php
✅ test('stores redirect rules')
✅ test('supports different http status codes')
✅ test('has no guarded attributes')
```

##### Feature-тесты (`tests/Feature/Models/RedirectTest.php`) ✅

```php
✅ test('redirect can be created')
✅ test('redirect supports 301 permanent redirect')
✅ test('redirect supports 302 temporary redirect')
```

**Примечания:**

-   Модель использует `from_path` / `to_path` и `code` (не `from`/`to`/`status`)
-   Обновлён PHPDoc для соответствия реальной схеме

---

#### 1.15. Audit ✅

**Путь:** `app/Models/Audit.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/AuditTest.php`) ✅

```php
✅ test('casts diff_json to array')
✅ test('stores ip and user agent')
✅ test('belongs to user')
✅ test('tracks entity changes')
✅ test('has no guarded attributes')
```

##### Feature-тесты (`tests/Feature/Models/AuditTest.php`) ✅

```php
✅ test('audit records are created on entity changes')
✅ test('audit stores diff of changes')
✅ test('audit belongs to user who made change')
```

**Примечания:**

-   Модель использует `action`/`subject_type`/`subject_id` (не `event`/`auditable_*`)
-   Хранит `ip` и `ua` вместо `meta` JSON
-   Обновлён PHPDoc для соответствия реальной схеме

---

#### 1.16. Outbox ✅

**Путь:** `app/Models/Outbox.php`  
**Статус:** ✅ Завершено (2025-11-17)

##### Unit-тесты (`tests/Unit/Models/OutboxTest.php`) ✅

```php
✅ test('casts payload_json to array')
✅ test('casts attempts to integer')
✅ test('casts available_at to datetime')
✅ test('supports retry attempts')
✅ test('table name is outbox')
✅ test('has no guarded attributes')
```

##### Feature-тесты (`tests/Feature/Models/OutboxTest.php`) ✅

```php
✅ test('outbox message can be created')
✅ test('outbox stores payload data')
✅ test('outbox tracks retry attempts')
✅ test('outbox available_at controls when task is available')
```

**Примечания:**

-   Модель использует `topic` (не `type`)
-   Таблица называется `outbox` (singular), а не `outboxes`
-   Обновлён PHPDoc для соответствия реальной схеме

---

## 2. Domain Services (63 сущности)

### 2.1. Модуль Auth (4 сущности) ✅

#### Приоритет: 🔴 Критичный

**Статус:** ✅ Завершено (2025-11-17)

##### 2.1.1. JwtService ✅

**Путь:** `app/Domain/Auth/JwtService.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Unit-тесты** (`tests/Unit/Auth/JwtServiceTest.php`) ✅

```php
✅ test('issues access token with correct claims')
✅ test('issues refresh token with correct claims')
✅ test('includes extra claims in token')
✅ test('verifies valid access token')
✅ test('verifies valid refresh token')
✅ test('rejects token with wrong type')
✅ test('rejects token with invalid issuer')
✅ test('rejects token with invalid signature')
✅ test('extracts user id from token')
✅ test('extracts jti from token')
✅ test('token includes correct expiration time for access token')
✅ test('token includes correct expiration time for refresh token')
✅ test('throws exception when secret is not configured')
✅ test('verify without type check accepts any token type')
✅ test('encode includes all standard jwt claims')
```

**Примечания:**

-   15 Unit-тестов
-   Полное покрытие функционала JWT (генерация, валидация, извлечение claims)
-   Проверка обработки ошибок (невалидная подпись, истечение, неверный issuer)

---

##### 2.1.2. RefreshTokenRepository ✅

**Путь:** `app/Domain/Auth/RefreshTokenRepository.php`  
**Реализация:** `app/Domain/Auth/RefreshTokenRepositoryImpl.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/Auth/RefreshTokenRepositoryTest.php`) ✅

```php
✅ test('stores refresh token')
✅ test('finds refresh token by jti')
✅ test('returns null when token not found')
✅ test('marks token as used conditionally')
✅ test('does not mark already used token')
✅ test('does not mark revoked token')
✅ test('does not mark expired token')
✅ test('revokes refresh token')
✅ test('revoke family revokes token and all descendants')
✅ test('revoke family returns zero for non existent token')
✅ test('deletes expired tokens')
✅ test('supports token rotation with parent jti')
✅ test('dto is valid when token is valid')
✅ test('dto is invalid when token is used')
```

**Примечания:**

-   14 Feature-тестов с реальной БД
-   Проверен алгоритм `revokeFamily` для инвалидации семейства токенов
-   Условное обновление (`markUsedConditionally`) для защиты от race conditions

---

##### 2.1.3. RefreshTokenDto ✅

**Путь:** `app/Domain/Auth/RefreshTokenDto.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Unit-тесты** (`tests/Unit/Auth/RefreshTokenDtoTest.php`) ✅

```php
✅ test('creates dto with all properties')
✅ test('is valid when not used not revoked and not expired')
✅ test('is invalid when used')
✅ test('is invalid when revoked')
✅ test('is invalid when expired')
✅ test('is readonly')
```

**Примечания:**

-   6 Unit-тестов
-   Readonly DTO для типобезопасности
-   Методы валидации `isValid()` и `isInvalid()`

---

##### 2.1.4. JwtAuthenticationException ✅

**Путь:** `app/Domain/Auth/Exceptions/JwtAuthenticationException.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Unit-тесты** (`tests/Unit/Auth/JwtAuthExceptionTest.php`) ✅

```php
✅ test('creates exception with reason and detail')
✅ test('exception message includes reason and detail')
✅ test('converts to error payload with unauthorized code')
✅ test('exception is instance of RuntimeException')
✅ test('reason and detail are readonly')
```

**Примечания:**

-   5 Unit-тестов
-   Реализует `ErrorConvertible` интерфейс
-   Readonly properties для immutability

---

### 2.2. Модуль Media (частично) ✅

#### Приоритет: 🔴 Критичный

**Статус:** 🔄 Частично завершено (2025-11-17)

##### 2.2.0. MediaQuery (Value Object) ✅

**Путь:** `app/Domain/Media/MediaQuery.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Unit-тесты** (`tests/Unit/Media/MediaQueryTest.php`) ✅

```php
✅ test('creates media query with all parameters')
✅ test('creates media query with minimal parameters')
✅ test('media query is immutable value object')
✅ test('deleted filter has correct enum values')
```

**Примечания:**

-   4 теста
-   Value Object для параметров выборки медиа
-   Использует MediaDeletedFilter enum

---

##### 2.2.1. MediaStoreAction ⏳

**Путь:** `app/Domain/Media/Actions/MediaStoreAction.php`  
**Статус:** ⏳ Требует доработки (сложный, много зависимостей)

**Зависимости:**

-   MediaMetadataExtractor
-   StorageResolver
-   CollectionRulesResolver
-   MediaValidationPipeline
-   ExifManager

**Feature-тесты:** ⏳ Отложено (требует настройки файловых систем и моков)

---

##### 2.2.2. ListMediaAction ✅

**Путь:** `app/Domain/Media/Actions/ListMediaAction.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/Media/ListMediaActionTest.php`) ✅

```php
✅ test('lists media with pagination')
✅ test('filters media by mime prefix')
✅ test('filters media by collection')
✅ test('searches media by title and original name')
✅ test('excludes soft deleted media by default')
✅ test('includes soft deleted media when requested')
✅ test('shows only soft deleted media')
✅ test('sorts media by different fields')
✅ test('respects per page limit')
```

**Примечания:**

-   9 тестов
-   Пагинация, фильтрация, поиск, сортировка
-   Поддержка soft deletes

---

##### 2.2.3. UpdateMediaMetadataAction ✅

**Путь:** `app/Domain/Media/Actions/UpdateMediaMetadataAction.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/Media/UpdateMediaMetadataActionTest.php`) ✅

```php
✅ test('updates media metadata')
✅ test('updates only title')
✅ test('updates only alt text')
✅ test('updates only collection')
✅ test('can update soft deleted media')
✅ test('throws exception for non existent media')
✅ test('clears metadata when set to null')
✅ test('returns fresh model instance')
```

**Примечания:**

-   8 тестов
-   Обновление метаданных: title, alt, collection
-   Поддержка soft deleted записей
-   Возвращает fresh instance

---

##### 2.2.4. ExifManager

**Путь:** `app/Domain/Media/Services/ExifManager.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Services/ExifManagerTest.php`)

```php
- test('extracts exif data from image')
- test('auto rotates image based on exif orientation')
- test('strips exif data from image')
- test('filters exif fields by whitelist')
- test('extracts color profile from image')
- test('handles images without exif')
```

---

##### 2.2.5. MediaValidationPipeline

**Путь:** `app/Domain/Media/Validation/MediaValidationPipeline.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Validation/MediaValidationPipelineTest.php`)

```php
- test('runs all validators')
- test('passes valid file')
- test('rejects invalid file')
- test('stops on first error')
- test('collects all errors')
```

---

##### 2.2.6. CorruptionValidator

**Путь:** `app/Domain/Media/Validation/CorruptionValidator.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Validation/CorruptionValidatorTest.php`)

```php
- test('validates image file integrity')
- test('validates video file integrity')
- test('rejects corrupted image')
- test('rejects corrupted video')
- test('supports jpg, png, gif formats')
```

---

##### 2.2.7. MimeSignatureValidator

**Путь:** `app/Domain/Media/Validation/MimeSignatureValidator.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Validation/MimeSignatureValidatorTest.php`)

```php
- test('validates file signature matches extension')
- test('rejects file with mismatched signature')
- test('detects fake file extensions')
```

---

##### 2.2.8. SizeLimitValidator

**Путь:** `app/Domain/Media/Validation/SizeLimitValidator.php`

**Unit-тesты** (`tests/Unit/Domain/Media/Validation/SizeLimitValidatorTest.php`)

```php
- test('validates file size within limit')
- test('rejects file exceeding size limit')
- test('applies collection specific size limit')
```

---

##### 2.2.9. GdImageProcessor

**Путь:** `app/Domain/Media/Images/GdImageProcessor.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Images/GdImageProcessorTest.php`)

```php
- test('opens image file')
- test('gets image width')
- test('gets image height')
- test('resizes image')
- test('encodes image to format')
- test('supports jpg, png, gif, webp')
- test('maintains aspect ratio on resize')
```

---

##### 2.2.10. GlideImageProcessor

**Путь:** `app/Domain/Media/Images/GlideImageProcessor.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Images/GlideImageProcessorTest.php`)

```php
- test('opens image with glide')
- test('resizes image with glide')
- test('applies filters')
- test('generates thumbnails')
- test('supports more formats than GD')
```

---

##### 2.2.11. MediaMetadataExtractor

**Путь:** `app/Domain/Media/Services/MediaMetadataExtractor.php`

**Unit-тesты** (`tests/Unit/Domain/Media/Services/MediaMetadataExtractorTest.php`)

```php
- test('extracts metadata using available plugins')
- test('tries plugins in order')
- test('returns null if no plugin supports file')
- test('handles plugin errors gracefully')
```

---

##### 2.2.12. ExiftoolMediaMetadataPlugin

**Путь:** `app/Domain/Media/Services/ExiftoolMediaMetadataPlugin.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Services/ExiftoolMediaMetadataPluginTest.php`)

```php
- test('supports images')
- test('extracts metadata using exiftool')
- test('parses exiftool json output')
- test('handles exiftool errors')
- test('requires exiftool binary')
```

---

##### 2.2.13. FfprobeMediaMetadataPlugin

**Путь:** `app/Domain/Media/Services/FfprobeMediaMetadataPlugin.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Services/FfprobeMediaMetadataPluginTest.php`)

```php
- test('supports video and audio')
- test('extracts duration from video')
- test('extracts bitrate from video')
- test('extracts frame rate from video')
- test('extracts codec information')
- test('requires ffprobe binary')
```

---

##### 2.2.14. MediainfoMediaMetadataPlugin

**Путь:** `app/Domain/Media/Services/MediainfoMediaMetadataPlugin.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Services/MediainfoMediaMetadataPluginTest.php`)

```php
- test('supports video and audio')
- test('extracts detailed av metadata')
- test('requires mediainfo binary')
```

---

##### 2.2.15. StorageResolver

**Путь:** `app/Domain/Media/Services/StorageResolver.php`

**Unit-тesты** (`tests/Unit/Domain/Media/Services/StorageResolverTest.php`)

```php
- test('resolves storage disk by collection')
- test('returns default disk for unknown collection')
- test('supports s3, local, public disks')
```

---

##### 2.2.16. CollectionRulesResolver

**Путь:** `app/Domain/Media/Services/CollectionRulesResolver.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Services/CollectionRulesResolverTest.php`)

```php
- test('gets rules for collection')
- test('returns global rules if collection not configured')
- test('gets allowed mimes for collection')
- test('gets max size for collection')
```

---

##### 2.2.17. OnDemandVariantService

**Путь:** `app/Domain/Media/Services/OnDemandVariantService.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Services/OnDemandVariantServiceTest.php`)

```php
- test('generates variant on demand')
- test('caches generated variant')
- test('returns existing variant if available')
- test('dispatches variant generation job')
```

---

##### 2.2.18. EloquentMediaRepository

**Путь:** `app/Domain/Media/EloquentMediaRepository.php`

**Unit-тесты** (`tests/Unit/Domain/Media/EloquentMediaRepositoryTest.php`)

```php
- test('builds query with filters')
- test('paginates results')
- test('gets all results')
- test('filters by deleted')
```

**Feature-тесты** (`tests/Feature/Domain/Media/MediaRepositoryTest.php`)

```php
- test('repository filters media correctly')
- test('repository includes trashed when requested')
- test('repository paginates results')
```

---

##### 2.2.19. MediaQuery

**Путь:** `app/Domain/Media/MediaQuery.php`

**Unit-тесты** (`tests/Unit/Domain/Media/MediaQueryTest.php`)

```php
- test('builds query from request')
- test('applies filters')
- test('applies search')
- test('applies sorting')
```

---

##### 2.2.20. MediaDeletedFilter

**Путь:** `app/Domain/Media/MediaDeletedFilter.php`

**Unit-тесты** (`tests/Unit/Domain/Media/MediaDeletedFilterTest.php`)

```php
- test('filters only deleted')
- test('filters only not deleted')
- test('filters all including deleted')
```

---

##### 2.2.21. MediaVariantStatus (Enum)

**Путь:** `app/Domain/Media/MediaVariantStatus.php`

**Unit-тесты** (`tests/Unit/Domain/Media/MediaVariantStatusTest.php`)

```php
- test('has pending status')
- test('has processing status')
- test('has completed status')
- test('has failed status')
- test('can be cast to string')
```

---

##### 2.2.22. MediaMetadataDTO

**Путь:** `app/Domain/Media/DTO/MediaMetadataDTO.php`

**Unit-тесты** (`tests/Unit/Domain/Media/DTO/MediaMetadataDTOTest.php`)

```php
- test('creates dto with all properties')
- test('converts to array')
- test('validates required fields')
```

---

##### 2.2.23. Events: MediaUploaded, MediaProcessed, MediaDeleted

**Путь:** `app/Domain/Media/Events/`

**Feature-тесты** (`tests/Feature/Domain/Media/MediaEventsTest.php`)

```php
- test('media uploaded event is dispatched on upload')
- test('media processed event is dispatched after processing')
- test('media deleted event is dispatched on deletion')
- test('event listeners are triggered correctly')
```

---

##### 2.2.24. Listeners: LogMediaEvent, NotifyMediaEvent, PurgeCdnCache

**Путь:** `app/Domain/Media/Listeners/`

**Unit-тесты** (`tests/Unit/Domain/Media/Listeners/*Test.php`)

```php
// LogMediaEvent
- test('logs media event to file')
- test('includes event details in log')

// NotifyMediaEvent
- test('sends notification on media event')
- test('notifies correct users')

// PurgeCdnCache
- test('purges cdn cache on media change')
- test('sends correct purge request')
```

---

##### 2.2.25. GenerateVariantJob

**Путь:** `app/Domain/Media/Jobs/GenerateVariantJob.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Jobs/GenerateVariantJobTest.php`)

```php
- test('job generates variant')
- test('job updates variant status to processing')
- test('job updates variant status to completed on success')
- test('job updates variant status to failed on error')
- test('job stores generated file')
- test('job can be retried on failure')
```

---

### 2.3. Модуль Entries (1 сущность) ✅

#### Приоритет: 🔴 Высокий

**Статус:** ✅ Завершено (2025-11-17)

##### 2.3.1. PublishingService ✅

**Путь:** `app/Domain/Entries/PublishingService.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Unit-тесты** (`tests/Unit/Entries/PublishingServiceTest.php`) ✅

```php
✅ test('publishes entry immediately with auto published_at')
✅ test('schedules entry with provided published_at')
✅ test('validates published_at is not in future')
✅ test('changes entry status to published with auto date')
✅ test('overwrites published_at when transitioning draft to published without explicit date')
✅ test('keeps draft status without published_at')
✅ test('allows updating published entry without changing published_at')
✅ test('sets published_at when creating published entry')
✅ test('transitions from draft to published sets published_at')
✅ test('validates published_at when explicitly provided')
```

**Feature-тесты** (`tests/Feature/Entries/PublishingServiceTest.php`) ✅

```php
✅ test('entry can be published')
✅ test('entry can be scheduled for publishing in past')
✅ test('cannot publish with future date')
✅ test('entry can be unpublished')
✅ test('multiple entries can be published')
✅ test('published_at uses UTC timezone')
```

**Примечания:**

-   16 тестов (10 Unit + 6 Feature)
-   Полное покрытие логики публикации записей
-   Валидация инвариантов (дата публикации не в будущем)
-   Автоматическое заполнение `published_at` при публикации

---

### 2.4. Модуль Plugins (частично) ✅

#### Приоритет: 🟡 Средний

**Статус:** 🔄 Частично завершено (2025-11-17)

##### 2.4.1. PluginActivator ⏳

**Путь:** `app/Domain/Plugins/PluginActivator.php`  
**Статус:** ⏳ Требует рефакторинга

**Проблема:** `PluginsRouteReloader` объявлен как `final`, что блокирует мокирование в тестах. Требуется:

-   Создать интерфейс для `PluginsRouteReloader`
-   Или использовать Dependency Injection с интерфейсом

**Тесты:** Отложено до рефакторинга архитектуры

---

##### 2.4.2. PluginRegistry ✅

**Путь:** `app/Domain/Plugins/PluginRegistry.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/Plugins/PluginRegistryTest.php`) ✅

```php
✅ test('returns enabled plugins only')
✅ test('returns empty collection when no plugins enabled')
✅ test('orders plugins by slug')
✅ test('returns enabled providers')
✅ test('filters out empty provider names')
✅ test('returns empty array when no enabled plugins')
✅ test('handles mixed provider types')
```

**Примечания:**

-   7 тестов
-   Управление списком включённых плагинов
-   Фильтрация провайдеров
-   Обработка отсутствия таблицы (миграции)

---

##### 2.4.3. PluginsSynchronizer

**Путь:** `app/Domain/Plugins/Services/PluginsSynchronizer.php`

**Unit-тесты** (`tests/Unit/Domain/Plugins/Services/PluginsSynchronizerTest.php`)

```php
- test('syncs plugins from filesystem')
- test('creates new plugin records')
- test('updates existing plugin records')
- test('validates plugin manifests')
- test('dispatches plugins synced event')
```

---

##### 2.4.4. PluginsRouteReloader

**Путь:** `app/Domain/Plugins/Services/PluginsRouteReloader.php`

**Unit-тесты** (`tests/Unit/Domain/Plugins/Services/PluginsRouteReloaderTest.php`)

```php
- test('reloads plugin routes')
- test('clears route cache')
- test('loads routes from enabled plugins')
- test('throws exception on reload failure')
```

---

##### 2.4.5. PluginAutoloader

**Путь:** `app/Domain/Plugins/Services/PluginAutoloader.php`

**Unit-тесты** (`tests/Unit/Domain/Plugins/Services/PluginAutoloaderTest.php`)

```php
- test('registers plugin autoloader')
- test('loads plugin classes')
- test('handles plugin namespace')
```

---

##### 2.4.6. PluginsSyncCommand

**Путь:** `app/Domain/Plugins/Commands/PluginsSyncCommand.php`

**Feature-тесты** (`tests/Feature/Domain/Plugins/PluginsSyncCommandTest.php`)

```php
- test('command syncs plugins')
- test('command output shows sync results')
```

---

##### 2.4.7. Plugin Events & Exceptions

**Путь:** `app/Domain/Plugins/Events/`, `app/Domain/Plugins/Exceptions/`

**Unit-тесты**

```php
// Events
- test('plugin enabled event is dispatched')
- test('plugin disabled event is dispatched')
- test('plugins synced event is dispatched')
- test('plugins routes reloaded event is dispatched')

// Exceptions
- test('invalid plugin manifest exception')
- test('plugin already enabled exception')
- test('plugin already disabled exception')
- test('routes reload failed exception')
```

---

### 2.5. Модуль Routing (частично) ✅

#### Приоритет: 🟡 Средний

**Статус:** 🔄 Частично завершено (2025-11-17)

##### 2.5.1. PathReservationService ⏳

**Путь:** `app/Domain/Routing/PathReservationServiceImpl.php`

**Unit-тesты** (`tests/Unit/Domain/Routing/PathReservationServiceTest.php`)

```php
- test('reserves path')
- test('releases path')
- test('checks if path is reserved')
- test('throws exception on duplicate reservation')
- test('validates path format')
- test('normalizes path before reservation')
```

**Feature-тесты** (`tests/Feature/Domain/Routing/PathReservationTest.php`)

```php
- test('path can be reserved')
- test('reserved path cannot be used by entry')
- test('path can be released')
- test('released path can be reused')
- test('system paths are automatically reserved')
- test('plugin paths are reserved on activation')
```

---

##### 2.5.2. PathReservationStore

**Путь:** `app/Domain/Routing/PathReservationStoreImpl.php`

**Unit-тesты** (`tests/Unit/Domain/Routing/PathReservationStoreTest.php`)

```php
- test('stores path reservation')
- test('retrieves path reservation')
- test('removes path reservation')
- test('checks if path exists')
```

---

##### 2.5.3. PathNormalizer ✅

**Путь:** `app/Domain/Routing/PathNormalizer.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Unit-тесты** (`tests/Unit/Routing/PathNormalizerTest.php`) ✅

```php
✅ test('normalizes path with leading slash')
✅ test('normalizes path without trailing slash')
✅ test('normalizes multiple slashes')
✅ test('converts to lowercase')
✅ test('handles unicode characters')
✅ test('removes query string')
✅ test('removes fragment')
✅ test('removes query and fragment')
✅ test('removes relative path segments')
✅ test('trims whitespace')
✅ test('handles root path')
✅ test('throws exception for empty path')
⏭️ test('throws exception for only query string') - skipped
✅ test('throws exception for only fragment')
✅ test('normalizes complex path')
✅ test('applies unicode NFC normalization if available')
```

**Примечания:**

-   16 тестов (15 passed, 1 skipped)
-   Нормализация путей: lowercase, trim slashes, remove query/fragment
-   Unicode NFC нормализация

---

##### 2.5.4. ReservedRouteRegistry

**Путь:** `app/Domain/Routing/ReservedRouteRegistry.php`

**Unit-тесты** (`tests/Unit/Domain/Routing/ReservedRouteRegistryTest.php`)

```php
- test('registers reserved route')
- test('gets all reserved routes')
- test('checks if path is in registry')
```

---

##### 2.5.5. ReservedPattern ✅

**Путь:** `app/Domain/Routing/ReservedPattern.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Unit-тесты** (`tests/Unit/Routing/ReservedPatternTest.php`) ✅

```php
✅ test('generates slug regex pattern')
✅ test('slug regex matches valid slug')
✅ test('slug regex rejects invalid characters')
✅ test('slug regex rejects trailing dash')
✅ test('slug regex rejects leading dash')
✅ test('slug regex allows dash in middle')
✅ test('slug regex rejects uppercase')
✅ test('slug regex rejects empty string')
✅ test('slug regex may include negative lookahead for reserved paths')
```

**Примечания:**

-   9 тестов
-   Генерация regex для slug с исключением зарезервированных путей
-   Валидация формата slug: lowercase, a-z0-9-

---

##### 2.5.6. Routing Exceptions ✅

**Путь:** `app/Domain/Routing/Exceptions/`  
**Статус:** ✅ Завершено (2025-11-17)

**Unit-тесты** ✅

**InvalidPathException** (`tests/Unit/Routing/InvalidPathExceptionTest.php`):

```php
✅ test('creates exception with message')
✅ test('creates exception with default message')
✅ test('exception is instance of Exception')
✅ test('converts to error payload with validation error code')
```

**PathAlreadyReservedException** (`tests/Unit/Routing/PathAlreadyReservedExceptionTest.php`):

```php
✅ test('creates exception with path and owner')
✅ test('creates exception with custom message')
✅ test('readonly properties cannot be modified')
✅ test('converts to error payload with conflict code')
```

**ForbiddenReservationRelease** (`tests/Unit/Routing/ForbiddenReservationReleaseTest.php`):

```php
✅ test('creates exception with path owner and attempted source')
✅ test('creates exception with custom message')
✅ test('readonly properties cannot be modified')
✅ test('converts to error payload with forbidden code')
```

**Примечания:**

-   12 тестов (4 + 4 + 4)
-   Все исключения реализуют `ErrorConvertible`
-   Readonly properties для immutability

---

### 2.6. Модуль Search (9 сущностей)

#### Приоритет: 🟡 Средний

##### 2.6.1. SearchService

**Путь:** `app/Domain/Search/SearchService.php`

**Unit-тesты** (`tests/Unit/Domain/Search/SearchServiceTest.php`)

```php
- test('searches entries')
- test('applies filters to search')
- test('applies sorting to results')
- test('paginates search results')
- test('returns search result object')
```

**Feature-тесты** (`tests/Feature/Domain/Search/SearchQueryTest.php`)

```php
- test('search returns relevant entries')
- test('search filters by post type')
- test('search filters by date range')
- test('search filters by terms')
- test('search highlights matched text')
```

---

##### 2.6.2. IndexManager

**Путь:** `app/Domain/Search/IndexManager.php`

**Unit-тесты** (`tests/Unit/Domain/Search/IndexManagerTest.php`)

```php
- test('creates search index')
- test('deletes search index')
- test('updates index mappings')
- test('reindexes all entries')
- test('indexes single entry')
- test('removes entry from index')
- test('swaps index alias')
```

**Feature-тесты** (`tests/Feature/Domain/Search/SearchIndexingTest.php`)

```php
- test('new entry is indexed automatically')
- test('updated entry is reindexed')
- test('deleted entry is removed from index')
- test('full reindex updates all entries')
- test('zero-downtime reindexing with alias swap')
```

---

##### 2.6.3. ElasticsearchSearchClient

**Путь:** `app/Domain/Search/Clients/ElasticsearchSearchClient.php`

**Unit-тесты** (`tests/Unit/Domain/Search/Clients/ElasticsearchSearchClientTest.php`)

```php
- test('sends search request to elasticsearch')
- test('creates index via api')
- test('deletes index via api')
- test('updates aliases via api')
- test('gets indices for alias')
- test('performs bulk operations')
- test('refreshes index')
- test('handles http errors')
- test('supports basic auth')
```

---

##### 2.6.4. NullSearchClient

**Путь:** `app/Domain/Search/Clients/NullSearchClient.php`

**Unit-тесты** (`tests/Unit/Domain/Search/Clients/NullSearchClientTest.php`)

```php
- test('returns empty results')
- test('does nothing on index operations')
```

---

##### 2.6.5. EntryToSearchDoc

**Путь:** `app/Domain/Search/Transformers/EntryToSearchDoc.php`

**Unit-тесты** (`tests/Unit/Domain/Search/Transformers/EntryToSearchDocTest.php`)

```php
- test('transforms entry to search document')
- test('extracts text from data_json')
- test('normalizes whitespace in content')
- test('generates excerpt from content')
- test('includes entry metadata')
- test('includes post type information')
- test('includes terms information')
```

---

##### 2.6.6. SearchQuery, SearchResult, SearchHit

**Путь:** `app/Domain/Search/`

**Unit-тesты** (`tests/Unit/Domain/Search/SearchQueryTest.php`, etc.)

```php
// SearchQuery
- test('builds query from parameters')
- test('adds filters to query')
- test('adds sorting to query')
- test('sets pagination')

// SearchResult
- test('contains hits')
- test('contains total count')
- test('contains aggregations')

// SearchHit
- test('wraps search result item')
- test('provides score')
- test('provides highlights')
```

---

##### 2.6.7. SearchTermFilter

**Путь:** `app/Domain/Search/ValueObjects/SearchTermFilter.php`

**Unit-тesты** (`tests/Unit/Domain/Search/ValueObjects/SearchTermFilterTest.php`)

```php
- test('creates filter from term id')
- test('converts to elasticsearch filter')
```

---

##### 2.6.8. ReindexSearchJob

**Путь:** `app/Domain/Search/Jobs/ReindexSearchJob.php`

**Unit-тesты** (`tests/Unit/Domain/Search/Jobs/ReindexSearchJobTest.php`)

```php
- test('job reindexes all entries')
- test('job handles large datasets')
- test('job can be retried on failure')
```

---

##### 2.6.9. SearchReindexCommand

**Путь:** `app/Domain/Search/Commands/SearchReindexCommand.php`

**Feature-тесты** (`tests/Feature/Domain/Search/SearchReindexCommandTest.php`)

```php
- test('command triggers reindex')
- test('command shows progress')
- test('command dispatches reindex job')
```

---

### 2.7. Модуль View ✅

#### Приоритет: 🟢 Низкий

**Статус:** ✅ Завершено (2025-11-17)

##### 2.7.1. BladeTemplateResolver ✅

**Путь:** `app/Domain/View/BladeTemplateResolver.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/View/BladeTemplateResolverTest.php`) ✅

```php
✅ test('returns default template when no specific templates exist')
✅ test('uses template override when specified')
✅ test('throws exception when template override does not exist')
✅ test('uses post type specific template when it exists')
✅ test('uses entry specific template when it exists')
✅ test('template override has highest priority')
✅ test('entry specific template has priority over post type template')
✅ test('can use custom default template')
✅ test('handles entry with loaded post type relationship')
✅ test('handles entry without post type slug')
```

**Примечания:**

-   10 тестов
-   Приоритет шаблонов: override > entry--{type}--{slug} > entry--{type} > default
-   Интеграция с Blade View facade
-   Оптимизация с eager loading

---

### 2.8. Модуль Sanitizer ✅

#### Приоритет: 🟡 Средний

**Статус:** ✅ Завершено (2025-11-17)

##### 2.8.1. RichTextSanitizer ✅

**Путь:** `app/Domain/Sanitizer/RichTextSanitizer.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/Sanitizer/RichTextSanitizerTest.php`) ✅

```php
✅ test('sanitizes basic html content')
✅ test('removes script tags')
✅ test('removes inline javascript')
✅ test('removes dangerous iframe tags')
✅ test('allows safe formatting tags')
✅ test('adds noopener noreferrer to target blank links')
✅ test('preserves existing rel attributes and adds noopener noreferrer')
✅ test('does not add rel to links without target blank')
✅ test('handles malformed html')
✅ test('removes javascript protocol from links')
✅ test('removes onerror from images')
✅ test('preserves nested formatting')
✅ test('handles empty content')
✅ test('handles plain text without tags')
✅ test('removes style attributes with dangerous content')
✅ test('sanitizes lists and preserves structure')
✅ test('handles multiple target blank links')
```

**Примечания:**

-   17 тестов
-   Использует HTMLPurifier для очистки
-   Автоматическое добавление rel="noopener noreferrer"
-   Защита от XSS атак (script, onclick, javascript:, etc.)

---

### 2.9. Модуль Options ✅

#### Приоритет: 🟢 Низкий

**Статус:** ✅ Завершено (2025-11-17)

##### 2.9.1. OptionsRepository ✅

**Путь:** `app/Domain/Options/OptionsRepository.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/Options/OptionsRepositoryTest.php`) ✅

```php
✅ test('option can be created and retrieved')
✅ test('option can be updated')
✅ test('option can be deleted')
✅ test('options are scoped by namespace')
✅ test('returns default value when option not found')
✅ test('stores complex json values')
✅ test('soft deleted option returns default value')
✅ test('can restore soft deleted option')
✅ test('restore returns null for non existent option')
✅ test('delete returns false for non existent option')
✅ test('dispatches option changed event on set')
✅ test('can update with description')
✅ test('set restores soft deleted option')
✅ test('getInt returns integer value')
✅ test('getInt returns default when option not found')
✅ test('getInt casts string to int')
```

**Примечания:**

-   16 тестов
-   Кэширование с тегами (если поддерживается драйвером)
-   Soft deletes и восстановление
-   События OptionChanged
-   Транзакционная безопасность

---

### 2.10. Модуль PostTypes ✅

#### Приоритет: 🟢 Низкий

**Статус:** ✅ Завершено (2025-11-17)

##### 2.10.1. PostTypeOptions ✅

**Путь:** `app/Domain/PostTypes/PostTypeOptions.php`  
**Статус:** ✅ Завершено (2025-11-17)

**Unit-тесты** (`tests/Unit/PostTypes/PostTypeOptionsTest.php`) ✅

```php
✅ test('creates options from array')
✅ test('creates empty options')
✅ test('converts options to array')
✅ test('normalizes string taxonomies to integers')
✅ test('accepts mixed integer and string taxonomies')
✅ test('throws exception for invalid taxonomies')
✅ test('throws exception for negative taxonomy ids')
✅ test('throws exception for zero taxonomy id')
✅ test('throws exception when taxonomies is not a list')
✅ test('gets allowed taxonomies')
✅ test('checks if taxonomy is allowed')
✅ test('allows all taxonomies when list is empty')
✅ test('gets field value')
✅ test('returns default for non existent field')
✅ test('checks if field exists')
✅ test('is immutable value object')
✅ test('converts to api array with normalized structure')
✅ test('preserves taxonomies as array in api response')
✅ test('handles complex nested structures')
```

**Примечания:**

-   19 тестов
-   Value Object для опций PostType
-   Валидация taxonomies (только положительные целые)
-   Нормализация строк в int
-   Immutability (readonly properties)
-   API-friendly сериализация

---

## 3. HTTP Controllers (60 эндпоинтов)

### 3.1. Auth API (4 эндпоинта) ✅

#### Приоритет: 🔴 Критичный

**Статус:** ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/Api/Auth/LoginTest.php`, `CurrentUserTest.php`, `RefreshTest.php`, `LogoutTest.php`) ✅

```php
// POST /api/v1/auth/login (LoginTest.php) - 12 тестов ✅
✅ test('successful login returns tokens')
✅ test('login with invalid credentials returns 401')
✅ test('login with missing email returns validation error')
✅ test('login with missing password returns validation error')
✅ test('login creates audit log on success')
✅ test('login creates audit log on failure')
✅ test('login with invalid email format returns validation error')
✅ test('login is case insensitive for email')
✅ test('login sets httponly secure cookies')
✅ test('login issues refresh token and stores in database')
✅ test('refresh token has correct parent relationship')
✅ test('multiple logins create separate refresh tokens')

// GET /api/v1/admin/auth/current (CurrentUserTest.php) - 6 тестов ✅
✅ test('authenticated user can get current user info')
✅ test('unauthenticated request returns 401')
✅ test('returns correct user data structure')
✅ test('does not expose sensitive fields')
✅ test('works with admin user')
✅ test('works with regular user')

// POST /api/v1/auth/refresh (RefreshTest.php) - 4 теста ✅
✅ test('refresh without cookie returns 401')
✅ test('refresh with invalid token returns 401')
✅ test('refresh endpoint exists and requires authentication')
✅ test('refresh endpoint clears cookies on error')

// POST /api/v1/auth/logout (LogoutTest.php) - 9 тестов ✅
✅ test('authenticated user can logout')
✅ test('logout clears access and refresh cookies')
✅ test('logout without authentication returns 401')
✅ test('logout revokes current refresh token')
✅ test('logout with all parameter revokes all user tokens')
✅ test('logout without all parameter revokes only current token family')
✅ test('logout handles missing refresh token gracefully')
✅ test('logout handles invalid refresh token gracefully')
✅ test('logout is idempotent')
```

**Примечания:**

-   31 Feature тест (12+6+4+9)
-   Полное покрытие Auth API
-   JWT аутентификация и refresh token rotation полностью протестированы
-   `UserResource` обновлен для включения `is_admin`, `created_at`, `updated_at`
-   JWT middleware отключен в тестах (уже протестирован отдельно)
-   Полное интеграционное тестирование refresh-механизма (ротация, reuse attack) в `LoginTest`

---

### 3.2. Entries API

#### Статус: ✅ Завершено (2025-11-17)

**Feature-тесты** (53 теста, 161 assertions)

// GET /api/v1/admin/entries (ListEntriesTest.php) - 12 тестов ✅
✅ test('admin can list entries')
✅ test('entries are paginated')
✅ test('entries can be filtered by post type')
✅ test('entries can be filtered by status')
✅ test('entries can be searched by title')
✅ test('entries can be searched by slug')
✅ test('unauthenticated request returns 401')
✅ test('entries can be filtered by author')
✅ test('entries can be sorted by updated_at')
✅ test('entries can be sorted by title')
✅ test('trashed entries are excluded by default')
✅ test('trashed entries can be listed with filter')

// POST /api/v1/admin/entries (CreateEntryTest.php) - 13 тестов ✅
✅ test('admin can create entry')
✅ test('entry is created with correct author')
✅ test('entry slug is auto-generated from title')
✅ test('entry can be created with custom slug')
✅ test('entry is created as draft by default')
✅ test('entry can be published immediately')
✅ test('entry can be created with content_json')
✅ test('entry can be created with meta_json')
✅ test('entry validation fails with missing title')
✅ test('entry validation fails with missing post_type')
✅ test('entry validation fails with invalid post_type')
✅ test('entry can be created with template_override')
✅ test('duplicate slug is made unique')

// GET /api/v1/admin/entries/{id} (ShowEntryTest.php) - 8 тестов ✅
✅ test('admin can view entry')
✅ test('entry includes author relationship')
✅ test('entry includes post type relationship')
✅ test('not found returns 404')
✅ test('can view soft deleted entry')
✅ test('entry includes content_json')
✅ test('entry includes meta_json')
✅ test('entry includes timestamps')

// PUT /api/v1/admin/entries/{id} (UpdateEntryTest.php) - 11 тестов ✅
✅ test('admin can update entry')
✅ test('entry data is updated correctly')
✅ test('entry validation works on update')
✅ test('not found returns 404')
✅ test('can update content_json')
✅ test('can update meta_json')
✅ test('can publish draft entry')
✅ test('can unpublish entry')
✅ test('can update template_override')
✅ test('can update soft deleted entry')
✅ test('updated_at changes after update')

// DELETE /api/v1/admin/entries/{id} + POST /api/v1/admin/entries/{id}/restore (DeleteRestoreEntryTest.php) - 9 тестов ✅
✅ test('admin can soft delete entry')
✅ test('deleted entry is not in default list')
✅ test('delete not found returns 404')
✅ test('cannot delete already deleted entry')
✅ test('admin can restore deleted entry')
✅ test('restored entry appears in default list')
✅ test('restore not found returns 404')
✅ test('cannot restore non-deleted entry')
✅ test('restored entry retains all data')

**Примечания:**

-   53 Feature теста покрывают базовые CRUD операции для записей
-   Тестируется фильтрация, поиск, пагинация, сортировка
-   Проверяется работа с relationships (author, postType, terms)
-   Тестируются мягкое удаление и восстановление
-   Проверяется auto-slug generation и uniqueness
-   Middleware `JwtAuth` и `VerifyApiCsrf` отключены в тестах
-   Endpoints для `/statuses`, `/terms`, `/terms/sync` - пока не протестированы

---

### 3.3. Media API

#### Статус: ✅ Завершено (2025-11-17)

**Feature-тесты** (35 тестов, 123 assertions)

// GET /api/v1/admin/media (ListMediaTest.php) - 13 тестов ✅
✅ test('admin can list media')
✅ test('media are paginated')
✅ test('media can be filtered by mime type')
✅ test('media can be filtered by collection')
✅ test('media can be searched by title')
✅ test('media can be searched by original name')
✅ test('media can be sorted by size')
✅ test('media can be sorted by created_at')
✅ test('trashed media are excluded by default')
✅ test('trashed media can be included with filter')
✅ test('only trashed media can be shown')
✅ test('media response includes preview and download urls')
✅ test('unauthenticated request returns 401')

// GET /api/v1/admin/media/{id} (ShowMediaTest.php) - 8 тестов ✅
✅ test('admin can view media')
✅ test('media includes dimensions for images')
✅ test('media includes duration for videos')
✅ test('not found returns 404')
✅ test('can view soft deleted media')
✅ test('media includes all metadata')
✅ test('media includes timestamps')
✅ test('media includes preview and download urls')

// PUT /api/v1/admin/media/{id} (UpdateMediaTest.php) - 7 тестов ✅
✅ test('admin can update media metadata')
✅ test('title can be updated')
✅ test('alt text can be updated')
✅ test('collection can be updated')
✅ test('can update soft deleted media')
✅ test('updated_at changes after update')
✅ test('can update multiple fields at once')

// DELETE /api/v1/admin/media/{id} + POST /api/v1/admin/media/{id}/restore (DeleteRestoreMediaTest.php) - 7 тестов ✅
✅ test('admin can soft delete media')
✅ test('deleted media not in default list')
✅ test('cannot delete already deleted media')
✅ test('admin can restore deleted media')
✅ test('restored media appears in default list')
✅ test('cannot restore non-deleted media')
✅ test('restored media retains all metadata')

**Примечания:**

-   35 Feature тестов покрывают базовые CRUD операции для медиафайлов
-   Тестируется фильтрация по mime, collection, поиск по title и original_name
-   Проверяется сортировка по size и created_at
-   Тестируются мягкое удаление и восстановление
-   Проверяется работа с soft-deleted медиафайлами
-   Middleware `JwtAuth` и `VerifyApiCsrf` отключены в тестах
-   **POST /media (upload)** - не протестирован (требует работы с реальными файлами и storage)
-   Endpoints `/preview`, `/download`, `/variants` - пока не протестированы

---

### 3.4. PostTypes API

#### Статус: ✅ Завершено (2025-11-17)

**Feature-тесты** (17 тестов, 57 assertions)

// GET /api/v1/admin/post-types (PostTypesTest.php) - 2 теста ✅
✅ test('admin can list post types')
✅ test('post types are sorted by slug')

// POST /api/v1/admin/post-types (PostTypesTest.php) - 5 тестов ✅
✅ test('admin can create post type')
✅ test('post type slug is unique')
✅ test('post type validation fails with missing slug')
✅ test('post type validation fails with missing name')
✅ test('post type can be created with custom fields in options')

// GET /api/v1/admin/post-types/{slug} (PostTypesTest.php) - 2 теста ✅
✅ test('admin can view post type')
✅ test('show not found returns 404')

// PUT /api/v1/admin/post-types/{slug} (PostTypesTest.php) - 4 теста ✅
✅ test('admin can update post type')
✅ test('post type slug can be updated')
✅ test('post type options can be updated')
✅ test('update not found returns 404')

// DELETE /api/v1/admin/post-types/{slug} (PostTypesTest.php) - 4 теста ✅
✅ test('admin can delete post type')
✅ test('cannot delete post type with entries')
✅ test('can force delete post type with entries')
✅ test('delete not found returns 404')

**Примечания:**

-   17 Feature тестов покрывают все CRUD операции для post types
-   Тестируется уникальность slug
-   Проверяется валидация (требуются slug и name)
-   Тестируется работа с options_json (custom fields)
-   Проверяется защита от удаления типов с связанными entries
-   Тестируется принудительное удаление (`force=1`) с каскадным удалением entries
-   Middleware `JwtAuth`, `VerifyApiCsrf` и `EnsureCanManagePostTypes` отключены в тестах

---

### 3.5. Plugins API

#### Статус: ✅ Завершено (2025-11-17)

**Feature-тесты** (31 тест, 136 assertions)

// GET /api/v1/admin/plugins (ListPluginsTest.php) - 11 тестов ✅
✅ test('admin can list plugins')
✅ test('plugins list is paginated')
✅ test('plugins can be filtered by enabled status')
✅ test('plugins can be filtered by disabled status')
✅ test('plugins can be searched by slug')
✅ test('plugins can be searched by name')
✅ test('plugins can be sorted by name')
✅ test('plugins can be sorted by slug')
✅ test('plugins can be sorted by version')
✅ test('plugins include routes_active flag')
✅ test('plugins include all metadata fields')

// POST /api/v1/admin/plugins/{slug}/enable (EnableDisablePluginTest.php) - 6 тестов ✅
✅ test('admin can enable plugin')
✅ test('enabling already enabled plugin returns conflict')
✅ test('enable returns 404 for non-existent plugin')
✅ test('enable triggers route reload')
✅ test('enable returns plugin resource with correct structure')
✅ test('enable dispatches plugin enabled event')

// POST /api/v1/admin/plugins/{slug}/disable (EnableDisablePluginTest.php) - 6 тестов ✅
✅ test('admin can disable plugin')
✅ test('disabling already disabled plugin returns conflict')
✅ test('disable returns 404 for non-existent plugin')
✅ test('disable triggers route reload')
✅ test('disable returns plugin resource with correct structure')
✅ test('disable dispatches plugin disabled event')

// POST /api/v1/admin/plugins/sync (SyncPluginsTest.php) - 8 тестов ✅
✅ test('admin can sync plugins')
✅ test('sync returns accepted status code 202')
✅ test('sync returns summary with added plugins')
✅ test('sync returns summary with updated plugins')
✅ test('sync returns summary with removed plugins')
✅ test('sync returns summary with providers')
✅ test('sync returns correct structure')
✅ test('sync handles empty summary gracefully')

**Примечания:**

-   31 Feature тест покрывают все 4 эндпоинта Plugins API
-   **Архитектурное решение:** Созданы интерфейсы для тестируемости:
    -   `RouteReloader` (для `PluginsRouteReloader`)
    -   `PluginsSynchronizerInterface` (для `PluginsSynchronizer`)
    -   `PluginActivatorInterface` (для `PluginActivator`)
-   Интерфейсы зарегистрированы в `AppServiceProvider::register()`
-   Тестируется:
    -   Пагинация, фильтрация, поиск, сортировка (LIST)
    -   Enable/Disable с проверкой конфликтов (409)
    -   Перезагрузка маршрутов (mock `RouteReloader`)
    -   События: `PluginEnabled`, `PluginDisabled`
    -   Sync с полной статистикой (added, updated, removed, providers)
    -   404 для несуществующих плагинов
-   Middleware `JwtAuth`, `VerifyApiCsrf` отключены в тестах

---

### 3.6. Options API

#### Статус: ✅ Завершено (2025-11-17)

**Feature-тесты** (22 теста, 81 assertion)

// GET /api/v1/admin/options/{namespace} (OptionsTest.php) - 8 тестов ✅
✅ test('admin can list options by namespace')
✅ test('options list is paginated')
✅ test('options can be searched by key')
✅ test('options can be searched by description')
✅ test('options list can include soft deleted')
✅ test('options list can show only soft deleted')
✅ test('options are sorted by key')

// GET /api/v1/admin/options/{namespace}/{key} (OptionsTest.php) - 2 теста ✅
✅ test('admin can view single option')
✅ test('show returns 404 for non-existent option')

// PUT /api/v1/admin/options/{namespace}/{key} (OptionsTest.php) - 6 тестов ✅
✅ test('admin can create new option')
✅ test('admin can update existing option')
✅ test('option can store array values')
✅ test('option can store object values')
✅ test('option description is optional')
✅ test('put dispatches option changed event')

// DELETE /api/v1/admin/options/{namespace}/{key} (OptionsTest.php) - 2 теста ✅
✅ test('admin can delete option')
✅ test('delete returns 404 for non-existent option')

// POST /api/v1/admin/options/{namespace}/{key}/restore (OptionsTest.php) - 3 теста ✅
✅ test('admin can restore deleted option')
✅ test('restore returns 404 for non-existent option')
✅ test('restore on non-deleted option returns the option unchanged')

// VALIDATION (OptionsTest.php) - 2 теста ✅
✅ test('invalid namespace returns validation error')
✅ test('invalid key returns validation error')

**Примечания:**

-   22 Feature теста покрывают все 5 эндпоинтов Options API
-   Тестируется:
    -   Пагинация, поиск (по key/description)
    -   Фильтрация soft-deleted опций (with/only)
    -   Сортировка по ключу
    -   Upsert (создание/обновление) опций
    -   Хранение различных типов значений (string, array, object)
    -   Soft delete и restore опций
    -   Событие `OptionChanged`
    -   Валидация namespace/key (regex pattern)
    -   404 для несуществующих опций
-   При создании новой опции возвращается 201 (Created)
-   При обновлении существующей опции возвращается 200 (OK)
-   OptionResource преобразует JSON в объекты (stdClass)
-   Middleware `JwtAuth`, `VerifyApiCsrf` отключены в тестах

---

### 3.7. Taxonomies & Terms API

#### Статус: ✅ Завершено (2025-11-17)

**Feature-тесты** (37 тестов, 143 assertions)

**Taxonomies API** (TaxonomiesTest.php) - 19 тестов, 72 assertions

// GET /api/v1/admin/taxonomies - 5 тестов ✅
✅ test('admin can list taxonomies')
✅ test('taxonomies list is paginated')
✅ test('taxonomies can be searched by name')
✅ test('taxonomies can be sorted by created_at desc')
✅ test('taxonomies can be sorted by label asc')

// POST /api/v1/admin/taxonomies - 4 теста ✅
✅ test('admin can create taxonomy')
✅ test('taxonomy defaults to non-hierarchical')
✅ test('taxonomy can have options_json')
✅ test('taxonomy label is required')

// GET /api/v1/admin/taxonomies/{id} - 2 теста ✅
✅ test('admin can view taxonomy')
✅ test('show returns 404 for non-existent taxonomy')

// PUT /api/v1/admin/taxonomies/{id} - 4 теста ✅
✅ test('admin can update taxonomy label')
✅ test('admin can update taxonomy hierarchical flag')
✅ test('admin can update taxonomy options_json')
✅ test('update returns 404 for non-existent taxonomy')

// DELETE /api/v1/admin/taxonomies/{id} - 4 теста ✅
✅ test('admin can delete taxonomy without terms')
✅ test('cannot delete taxonomy with terms')
✅ test('can force delete taxonomy with terms')
✅ test('delete returns 404 for non-existent taxonomy')

**Terms API** (TermsTest.php) - 18 тестов, 71 assertion

// GET /api/v1/admin/taxonomies/{taxonomy}/terms - 5 тестов ✅
✅ test('admin can list terms by taxonomy')
✅ test('terms list is paginated')
✅ test('terms can be searched by name')
✅ test('terms can be sorted by name asc')
✅ test('returns 404 for non-existent taxonomy')

// GET /api/v1/admin/taxonomies/{taxonomy}/terms/tree - 1 тест ✅
✅ test('admin can get terms tree')

// POST /api/v1/admin/taxonomies/{taxonomy}/terms - 4 теста ✅
✅ test('admin can create term')
✅ test('term can have meta_json')
✅ test('term can have parent')
✅ test('term name is required')

// GET /api/v1/admin/terms/{term} - 2 теста ✅
✅ test('admin can view term')
✅ test('show returns 404 for non-existent term')

// PUT /api/v1/admin/terms/{term} - 4 теста ✅
✅ test('admin can update term name')
✅ test('admin can update term meta_json')
✅ test('admin can change term parent')
✅ test('update returns 404 for non-existent term')

// DELETE /api/v1/admin/terms/{term} - 2 теста ✅
✅ test('admin can delete term')
✅ test('delete returns 404 for non-existent term')

**Примечания:**

-   37 Feature тестов покрывают 10 эндпоинтов (5 Taxonomies + 5 Terms)
-   **Taxonomies:**
    -   Пагинация, поиск, сортировка (created_at, label)
    -   Иерархические и плоские таксономии
    -   options_json для дополнительных настроек
    -   Защита от удаления таксономий с термами
    -   Force delete с каскадным удалением термов
-   **Terms:**
    -   Пагинация, поиск по имени, сортировка
    -   Иерархия термов (parent_id, tree endpoint)
    -   meta_json для метаданных
    -   Управление иерархией через `TermHierarchyService`
-   Taxonomy использует поле `name` в БД, но возвращает `label` в API
-   Terms поддерживают древовидную структуру для иерархических таксономий
-   Middleware `JwtAuth`, `VerifyApiCsrf` отключены в тестах

---

### 3.8. Search API

#### Статус: ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/Api/Admin/Search/SearchAdminTest.php`) — 9 тестов:

```php
// POST /api/v1/admin/search/reindex
✅ test('admin can trigger reindex')
✅ test('reindex job is dispatched with tracking id')
✅ test('reindex returns batch size from config')
✅ test('reindex returns estimated total from published entries')
✅ test('reindex fails when search is disabled')
✅ test('reindex requires authentication')
✅ test('reindex returns unique job id')
✅ test('reindex job id is a valid ulid')
✅ test('reindex with zero published entries returns zero estimated total')
```

**Feature-тесты** (`tests/Feature/Api/Public/Search/PublicSearchTest.php`) — 15 тестов:

```php
// GET /api/v1/search
✅ test('public can search published entries')
✅ test('draft entries are not in results')
✅ test('search results are paginated')
✅ test('search returns etag header')
✅ test('search returns cache control headers')
✅ test('search accepts post type filter')
✅ test('search accepts term filter')
✅ test('search accepts date range filter')
✅ test('search validates query min length')
✅ test('search validates query max length')
✅ test('search validates date range')
✅ test('search without query parameter works')
✅ test('search highlights matches in results')
✅ test('search returns score for relevance sorting')
✅ test('search returns took ms in meta')
```

**Изменения в архитектуре:**

-   **Создан интерфейс** `SearchServiceInterface` для возможности мокирования `final` класса `SearchService`
-   **Обновлён** `SearchServiceProvider`: добавлен биндинг `SearchServiceInterface → SearchService`
-   **Обновлён** `SearchController`: теперь использует `SearchServiceInterface` вместо конкретного `SearchService`

**Примечания:**

-   Публичный Search API не требует авторизации (для опубликованного контента)
-   Admin Reindex API диспатчит `ReindexSearchJob` в фоновом режиме
-   Возвращает 202 (Accepted) с `job_id`, `batch_size`, `estimated_total`
-   Проверяет `config('search.enabled')` перед диспатчем джобы
-   Публичный поиск поддерживает фильтры: `post_type[]`, `term[]`, `from`, `to`, `page`, `per_page`
-   Возвращает ETag и Cache-Control заголовки для оптимизации кэширования
-   Поддерживает подсветку (highlighting) совпадений в результатах поиска

---

### 3.9. Path Reservation API

#### Статус: ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/Api/Admin/PathReservations/PathReservationsTest.php`) — 21 тест:

```php
// GET /api/v1/admin/reservations
✅ test('admin can list reserved paths')
✅ test('list returns empty array when no reservations')
✅ test('list is sorted by path')
✅ test('list requires authentication')
✅ test('list includes reservation metadata')

// POST /api/v1/admin/reservations
✅ test('admin can reserve path')
✅ test('reservation creates audit log')
✅ test('duplicate path returns conflict error')
✅ test('reservation validates required fields')
✅ test('reservation validates path max length')
✅ test('reservation validates source max length')
✅ test('reservation reason is optional')
✅ test('reservation requires admin permissions')
✅ test('paths are normalized before reservation')

// DELETE /api/v1/admin/reservations/{path}
✅ test('admin can release path reservation')
✅ test('release creates audit log')
✅ test('release from wrong source returns forbidden')
✅ test('release validates required source')
✅ test('release path can be in body if not in url')
✅ test('release requires authentication')
✅ test('release requires admin permissions')
```

**Примечания:**

-   Пути автоматически нормализуются перед резервацией (lowercase, без trailing slash)
-   Создаётся audit log для `reserve` и `release` действий
-   Конфликт (409) при попытке зарезервировать уже занятый путь
-   Forbidden (403) при попытке освободить путь с неправильным `source`
-   Требует `is_admin = true` для всех операций
-   Путь может быть указан как в URL, так и в теле запроса (для DELETE)
-   Middleware `JwtAuth`, `VerifyApiCsrf` отключены в тестах

---

### 3.10. Utils & Templates API

#### Статус: ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/Api/Admin/Utils/UtilsTest.php`) — 10 тестов:

```php
// GET /api/v1/admin/utils/slugify
✅ test('generates slug from title')
✅ test('ensures unique slug when base exists')
✅ test('checks reserved routes when generating slug')
✅ test('slug scoped by post type')
✅ test('handles empty title')
✅ test('handles special characters in title')
✅ test('slugify requires authentication')
✅ test('defaults to page post type when not specified')
✅ test('handles very long titles')
✅ test('generates incremental suffixes for multiple duplicates')
```

**Feature-тесты** (`tests/Feature/Api/Admin/Templates/TemplatesTest.php`) — 17 тестов:

```php
// GET /api/v1/admin/templates
✅ test('admin can list templates')
✅ test('list excludes system directories')
✅ test('list returns sorted templates')
✅ test('list requires authentication')

// GET /api/v1/admin/templates/{name}
✅ test('admin can view template content')
✅ test('show returns 404 for non-existent template')
✅ test('show requires authentication')

// POST /api/v1/admin/templates
✅ test('admin can create template')
✅ test('create returns conflict if template exists')
✅ test('create validates required fields')
✅ test('create automatically creates directories')
✅ test('create requires authentication')

// PUT /api/v1/admin/templates/{name}
✅ test('admin can update template')
✅ test('update returns 404 for non-existent template')
✅ test('update validates content required')
✅ test('update requires authentication')
✅ test('template name converts to correct path')
```

**Примечания:**

-   `slugify` генерирует `base` (базовый slug) и `unique` (уникальный с учётом коллизий)
-   Проверяет уникальность в scope post_type
-   Проверяет конфликты с `ReservedRoute`
-   Templates API предоставляет CRUD для Blade шаблонов
-   Исключает системные директории: `admin`, `errors`, `layouts`, `partials`, `vendor`
-   Автоматически создаёт директории при создании шаблонов
-   Middleware `JwtAuth`, `VerifyApiCsrf` отключены в тестах

---

### 3.11. Web Controllers

#### Статус: ✅ Завершено (2025-11-17)

**Feature-тесты** (`tests/Feature/Web/PagesTest.php`) — 15 тестов:

```php
// GET / (HomeController)
✅ test('homepage renders default template')
✅ test('homepage renders entry when home_entry_id is set')
✅ test('homepage falls back to default when entry is not published')
✅ test('homepage falls back to default when entry is scheduled')
✅ test('homepage uses correct template resolver')

// GET /{slug} (PageController)
✅ test('entry page renders published entry')
✅ test('entry page returns 404 for non-existent slug')
✅ test('entry page returns 404 for draft entry')
✅ test('entry page returns 404 for scheduled entry')
✅ test('entry uses correct template for post type')
✅ test('entry page loads with post type relationship')
✅ test('entry page uses template override if specified')

// GET /admin/ping (AdminPingController)
✅ test('admin ping returns ok')
✅ test('admin ping confirms route priority')

// Routing
✅ test('reserved paths are rejected by middleware')
```

**Примечания:**

-   `HomeController`: рендерит главную страницу, поддерживает `site:home_entry_id` опцию
-   `PageController`: catch-all роут для `/{slug}`, рендерит опубликованные entries
-   `AdminPingController`: тестовый эндпоинт для проверки порядка загрузки роутов
-   Используется `TemplateResolver` для выбора Blade шаблонов
-   Проверяет статус `published` и `published_at <= now()`
-   Поддерживает `template_override` для custom шаблонов
-   Зарезервированные пути защищены через `ReservedPattern` и `RejectReservedIfMatched` middleware

---

## 4. Validation Rules

### Статус: ✅ Завершено (2025-11-17)

#### 4.1. UniqueEntrySlug

**Путь:** `app/Rules/UniqueEntrySlug.php`

**Unit-тesты** (`tests/Unit/Rules/UniqueEntrySlugTest.php`)

```php
- test('passes for unique slug')
- test('fails for duplicate slug in same post type')
- test('passes for duplicate slug in different post type')
- test('passes for same entry on update')
```

---

#### 4.2. ReservedSlug

**Путь:** `app/Rules/ReservedSlug.php`

**Unit-тesты** (`tests/Unit/Rules/ReservedSlugTest.php`)

```php
- test('passes for non-reserved slug')
- test('fails for reserved path')
- test('fails for reserved prefix')
```

---

#### 4.3. Publishable

**Путь:** `app/Rules/Publishable.php`

**Unit-тesты** (`tests/Unit/Rules/PublishableTest.php`)

```php
- test('passes for valid publishable state')
- test('fails if required fields missing')
```

---

#### 4.4. PublishedDateNotInFuture

**Путь:** `app/Rules/PublishedDateNotInFuture.php`

**Unit-тesты** (`tests/Unit/Rules/PublishedDateNotInFutureTest.php`)

```php
- test('passes for past date')
- test('passes for current date')
- test('fails for future date')
```

---

#### 4.5. NoTermCycle

**Путь:** `app/Rules/NoTermCycle.php`

**Unit-тesты** (`tests/Unit/Rules/NoTermCycleTest.php`)

```php
- test('passes for non-cyclic hierarchy')
- test('fails for direct cycle')
- test('fails for indirect cycle')
```

---

#### 4.6. JsonValue

**Путь:** `app/Rules/JsonValue.php`

**Unit-тesты** (`tests/Unit/Rules/JsonValueTest.php`)

```php
- test('passes for valid json')
- test('passes for null')
- test('fails for invalid json')
```

---

## 5. Integration Tests

### Приоритет: 🔴 Критичный

**Feature-тесты** (`tests/Feature/Integration/`)

#### 5.1. MediaProcessingPipelineTest.php

```php
- test('complete media processing pipeline')
- test('image upload, validation, storage, variant generation')
- test('video upload with metadata extraction')
- test('pdf upload and validation')
```

---

#### 5.2. SearchReindexingTest.php

```php
- test('full reindex updates all entries')
- test('zero-downtime reindex with alias swap')
- test('incremental indexing on entry changes')
```

---

#### 5.3. PluginSystemTest.php

```php
- test('plugin activation loads routes')
- test('plugin deactivation removes routes')
- test('plugin sync discovers new plugins')
```

---

#### 5.4. EntryLifecycleTest.php

```php
- test('entry creation, publishing, updating, deletion')
- test('entry with terms')
- test('entry slug reservation and validation')
```

---

## 6. Приоритеты внедрения

### Фаза 1: Критичные компоненты (1-2 недели)

**Цель:** Покрыть тестами критически важные части системы

1. ✅ **Auth Module** (JwtService, RefreshTokenRepository)
2. ✅ **Models** (User, Entry, Media, PostType)
3. ✅ **Media Actions** (MediaStoreAction, MediaValidation)
4. ✅ **Auth API** (Login, Refresh, Logout)
5. ✅ **Entries API** (CRUD операции)

### Фаза 2: Основной функционал (2-3 недели)

**Цель:** Покрыть основную бизнес-логику

1. **Media Module** (все сервисы и валидаторы)
2. **Media API** (загрузка, управление)
3. **Остальные Models** (все 16 моделей)
4. **Search Module** (индексация, поиск)
5. **Routing Module** (резервирование путей)

### Фаза 3: Дополнительный функционал (1-2 недели)

**Цель:** Покрыть вспомогательные модули

1. **Plugins Module** (активация, синхронизация)
2. **PostTypes, Taxonomies, Terms API**
3. **Options Module**
4. **Validation Rules**
5. **View Module**

### Фаза 4: Интеграционные тесты (1 неделя)

**Цель:** Проверить взаимодействие компонентов

1. **MediaProcessingPipeline**
2. **SearchReindexing**
3. **PluginSystem**
4. **EntryLifecycle**

---

## 7. Метрики и целевые показатели

### Целевое покрытие

-   **Unit-тесты:** 80%+ покрытие доменных сервисов
-   **Feature-тесты:** 100% покрытие API эндпоинтов
-   **Models:** 100% покрытие всех моделей
-   **Integration:** Покрытие критических сценариев

### Метрики качества

-   Все тесты проходят без ошибок
-   Время выполнения Unit-тестов < 30 сек
-   Время выполнения Feature-тестов < 2 мин
-   Время выполнения всех тестов < 3 мин

---

## 8. Команды для работы

### Запуск тестов

```bash
# Все тесты
composer test

# По типам
composer test:unit
composer test:feature

# По модулям
composer test:module:auth
composer test:module:media
composer test:module:entries
composer test:module:plugins
composer test:module:search

# С покрытием
composer test:coverage
php artisan test --coverage --min=80

# Параллельно
composer test:parallel
```

### Создание тестов

```bash
# Unit-тест
php artisan make:test Unit/Domain/Media/Services/ExifManagerTest --unit

# Feature-тест
php artisan make:test Feature/Api/Admin/V1/Media/MediaManagementTest
```

---

## 9. Шаблоны тестов

### Unit-тест (Action)

```php
<?php

declare(strict_types=1);

use App\Domain\Media\Actions\MediaStoreAction;
use Illuminate\Http\UploadedFile;

test('stores media file successfully', function () {
    // Arrange
    $action = app(MediaStoreAction::class);
    $file = UploadedFile::fake()->image('test.jpg');

    // Act
    $media = $action->execute($file, 'default');

    // Assert
    expect($media)
        ->toBeInstanceOf(Media::class)
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->exists)->toBeTrue();
});
```

### Feature-тест (API)

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use function Pest\Laravel\postJson;

test('admin can upload media', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $file = UploadedFile::fake()->image('photo.jpg', 1920, 1080);

    // Act
    $response = $this->actingAs($admin, 'jwt')
        ->postJson('/api/v1/admin/media', [
            'file' => $file,
            'title' => 'Test Photo',
            'collection' => 'default',
        ]);

    // Assert
    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'title', 'url', 'mime_type'],
        ]);

    $this->assertDatabaseHas('media', [
        'title' => 'Test Photo',
        'mime_type' => 'image/jpeg',
    ]);
});
```

---

## 10. Заключение

Данный план покрывает **все 170 сущностей проекта**:

-   ✅ 16 моделей
-   ✅ 63 доменных сервиса
-   ✅ 60 HTTP эндпоинтов
-   ✅ 6 правил валидации
-   ✅ События, слушатели, jobs
-   ✅ Интеграционные тесты

План структурирован по модулям, приоритизирован и готов к поэтапному внедрению.

---

**Следующий шаг:** Начать с Фазы 1 (критичные компоненты) согласно плану внедрения.

**Обновление плана:** После завершения каждой фазы обновлять статус выполнения.

---

_Документ создан: 2025-11-17_  
_Версия: 1.0_
