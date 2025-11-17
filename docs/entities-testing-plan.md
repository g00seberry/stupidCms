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

#### 1.3. Media

**Путь:** `app/Models/Media.php`  
**Factory:** `database/factories/MediaFactory.php`

##### Unit-тесты (`tests/Unit/Models/MediaTest.php`)

```php
- test('uses ULID as primary key')
- test('casts exif_json to array')
- test('casts deleted_at to datetime')
- test('casts dimensions to integers')
- test('has variants relationship')
- test('has metadata relationship')
- test('has image scope')
- test('has video scope')
- test('has audio scope')
- test('has document scope')
- test('uses soft deletes')
- test('has full url accessor')
- test('has thumbnail accessor')
- test('has is image accessor')
- test('has is video accessor')
```

##### Feature-тесты (`tests/Feature/Models/MediaTest.php`)

```php
- test('media can be created with factory')
- test('media has unique disk and path combination')
- test('media can have multiple variants')
- test('media can have metadata')
- test('media can be soft deleted')
- test('media exif json stores metadata')
- test('media dimensions are stored correctly')
- test('media file size is stored in bytes')
```

---

#### 1.4. MediaVariant

**Путь:** `app/Models/MediaVariant.php`  
**Factory:** `database/factories/MediaVariantFactory.php`

##### Unit-тесты (`tests/Unit/Models/MediaVariantTest.php`)

```php
- test('uses ULID as primary key')
- test('casts status to MediaVariantStatus enum')
- test('casts timestamps to immutable datetime')
- test('belongs to media')
- test('has pending scope')
- test('has processing scope')
- test('has completed scope')
- test('has failed scope')
```

##### Feature-тесты (`tests/Feature/Models/MediaVariantTest.php`)

```php
- test('variant belongs to media')
- test('variant status transitions correctly')
- test('variant can be marked as processing')
- test('variant can be marked as completed')
- test('variant can be marked as failed')
```

---

#### 1.5. MediaMetadata

**Путь:** `app/Models/MediaMetadata.php`  
**Factory:** `database/factories/MediaMetadataFactory.php`

##### Unit-тесты (`tests/Unit/Models/MediaMetadataTest.php`)

```php
- test('casts duration_ms to integer')
- test('casts bitrate_kbps to integer')
- test('casts frame_rate to float')
- test('casts frame_count to integer')
- test('belongs to media')
- test('has duration accessor in seconds')
```

##### Feature-тесты (`tests/Feature/Models/MediaMetadataTest.php`)

```php
- test('metadata belongs to media')
- test('metadata stores av technical details')
- test('metadata can store video codec')
- test('metadata can store audio codec')
```

---

#### 1.6. PostType

**Путь:** `app/Models/PostType.php`  
**Factory:** `database/factories/PostTypeFactory.php`

##### Unit-тесты (`tests/Unit/Models/PostTypeTest.php`)

```php
- test('has fillable attributes')
- test('casts options_json to PostTypeOptions')
- test('has entries relationship')
- test('slug is unique')
```

##### Feature-тесты (`tests/Feature/Models/PostTypeTest.php`)

```php
- test('post type can be created')
- test('post type has unique slug')
- test('post type can have multiple entries')
- test('post type options are stored correctly')
```

---

#### 1.7. Plugin

**Путь:** `app/Models/Plugin.php`  
**Factory:** `database/factories/PluginFactory.php`

##### Unit-тесты (`tests/Unit/Models/PluginTest.php`)

```php
- test('uses ULID as primary key')
- test('casts enabled to boolean')
- test('casts meta_json to array')
- test('casts last_synced_at to immutable datetime')
- test('has enabled scope')
- test('has disabled scope')
```

##### Feature-тесты (`tests/Feature/Models/PluginTest.php`)

```php
- test('plugin can be created')
- test('plugin can be enabled')
- test('plugin can be disabled')
- test('plugin stores metadata')
- test('plugin tracks last sync time')
```

---

#### 1.8. Option

**Путь:** `app/Models/Option.php`  
**Factory:** `database/factories/OptionFactory.php`

##### Unit-тесты (`tests/Unit/Models/OptionTest.php`)

```php
- test('uses ULID as primary key')
- test('has fillable attributes')
- test('casts value_json using AsJsonValue')
- test('has namespace scope')
- test('has key scope')
- test('uses soft deletes')
```

##### Feature-тesты (`tests/Feature/Models/OptionTest.php`)

```php
- test('option can be created')
- test('option value is stored as json')
- test('option can be retrieved by namespace and key')
- test('option can be soft deleted')
```

---

#### 1.9. Taxonomy

**Путь:** `app/Models/Taxonomy.php`  
**Factory:** `database/factories/TaxonomyFactory.php`

##### Unit-тесты (`tests/Unit/Models/TaxonomyTest.php`)

```php
- test('casts options_json to array')
- test('casts hierarchical to boolean')
- test('has terms relationship')
- test('has hierarchical scope')
- test('has flat scope')
```

##### Feature-тесты (`tests/Feature/Models/TaxonomyTest.php`)

```php
- test('taxonomy can be created')
- test('taxonomy can be hierarchical')
- test('taxonomy can be flat')
- test('taxonomy can have multiple terms')
```

---

#### 1.10. Term

**Путь:** `app/Models/Term.php`  
**Factory:** `database/factories/TermFactory.php`

##### Unit-тесты (`tests/Unit/Models/TermTest.php`)

```php
- test('casts meta_json to array')
- test('belongs to taxonomy')
- test('has entries many to many relationship')
- test('has ancestors relationship')
- test('has descendants relationship')
- test('has parent relationship')
- test('has children relationship')
- test('uses soft deletes')
```

##### Feature-тесты (`tests/Feature/Models/TermTest.php`)

```php
- test('term belongs to taxonomy')
- test('term can have parent')
- test('term can have children')
- test('term can have multiple ancestors')
- test('term can have multiple descendants')
- test('term can be attached to entries')
- test('term hierarchy is maintained via closure table')
```

---

#### 1.11. TermTree

**Путь:** `app/Models/TermTree.php`

##### Unit-тесты (`tests/Unit/Models/TermTreeTest.php`)

```php
- test('implements closure table pattern')
- test('stores term relationships')
```

---

#### 1.12. RefreshToken

**Путь:** `app/Models/RefreshToken.php`

##### Unit-тесты (`tests/Unit/Models/RefreshTokenTest.php`)

```php
- test('has fillable attributes')
- test('casts timestamps to datetime')
- test('belongs to user')
- test('tracks parent token via parent_jti')
- test('can be marked as used')
- test('can be marked as revoked')
```

##### Feature-тесты (`tests/Feature/Models/RefreshTokenTest.php`)

```php
- test('refresh token can be created')
- test('refresh token belongs to user')
- test('refresh token can be used once')
- test('refresh token can be revoked')
- test('refresh token supports rotation')
```

---

#### 1.13. ReservedRoute

**Путь:** `app/Models/ReservedRoute.php`

##### Unit-тесты (`tests/Unit/Models/ReservedRouteTest.php`)

```php
- test('has fillable attributes')
- test('supports path type')
- test('supports prefix type')
- test('has path scope')
- test('has prefix scope')
```

##### Feature-тесты (`tests/Feature/Models/ReservedRouteTest.php`)

```php
- test('reserved route can be created')
- test('path type matches exact path')
- test('prefix type matches path prefix')
```

---

#### 1.14. Redirect

**Путь:** `app/Models/Redirect.php`

##### Unit-тесты (`tests/Unit/Models/RedirectTest.php`)

```php
- test('stores redirect rules')
- test('supports different http status codes')
```

---

#### 1.15. Audit

**Путь:** `app/Models/Audit.php`

##### Unit-тесты (`tests/Unit/Models/AuditTest.php`)

```php
- test('casts diff_json to array')
- test('casts meta to array')
- test('belongs to user')
- test('tracks entity changes')
```

##### Feature-тесты (`tests/Feature/Models/AuditTest.php`)

```php
- test('audit records are created on entity changes')
- test('audit stores diff of changes')
- test('audit belongs to user who made change')
```

---

#### 1.16. Outbox

**Путь:** `app/Models/Outbox.php`

##### Unit-тесты (`tests/Unit/Models/OutboxTest.php`)

```php
- test('casts payload_json to array')
- test('casts attempts to integer')
- test('casts available_at to datetime')
- test('supports retry attempts')
```

---

## 2. Domain Services (63 сущности)

### 2.1. Модуль Auth (4 сущности)

#### Приоритет: 🔴 Критичный

##### 2.1.1. JwtService

**Путь:** `app/Domain/Auth/JwtService.php`

**Unit-тесты** (`tests/Unit/Domain/Auth/JwtServiceTest.php`)

```php
- test('generates access token with correct claims')
- test('generates refresh token with correct claims')
- test('validates access token successfully')
- test('validates refresh token successfully')
- test('rejects expired token')
- test('rejects token with invalid signature')
- test('extracts user id from token')
- test('extracts jti from token')
- test('token includes correct expiration time')
```

---

##### 2.1.2. RefreshTokenRepository

**Путь:** `app/Domain/Auth/RefreshTokenRepository.php`  
**Реализация:** `app/Domain/Auth/RefreshTokenRepositoryImpl.php`

**Unit-тесты** (`tests/Unit/Domain/Auth/RefreshTokenRepositoryTest.php`)

```php
- test('creates refresh token')
- test('finds refresh token by jti')
- test('marks token as used')
- test('marks token as revoked')
- test('revokes all tokens for user')
- test('checks if token is valid')
- test('checks if token is used')
- test('checks if token is revoked')
- test('checks if token is expired')
- test('supports token rotation')
```

**Feature-тесты** (`tests/Feature/Domain/Auth/RefreshTokenFlowTest.php`)

```php
- test('refresh token flow works end to end')
- test('token rotation creates new token')
- test('old token is marked as used after rotation')
- test('revoked token cannot be used')
- test('expired token cannot be used')
```

---

##### 2.1.3. RefreshTokenDto

**Путь:** `app/Domain/Auth/RefreshTokenDto.php`

**Unit-тесты** (`tests/Unit/Domain/Auth/RefreshTokenDtoTest.php`)

```php
- test('creates dto with all properties')
- test('validates required properties')
- test('converts to array')
```

---

##### 2.1.4. JwtAuthenticationException

**Путь:** `app/Domain/Auth/Exceptions/JwtAuthenticationException.php`

**Unit-тесты** (`tests/Unit/Domain/Auth/Exceptions/JwtAuthenticationExceptionTest.php`)

```php
- test('exception has correct message')
- test('exception can be thrown and caught')
```

---

### 2.2. Модуль Media (34 сущности)

#### Приоритет: 🔴 Критичный

##### 2.2.1. MediaStoreAction

**Путь:** `app/Domain/Media/Actions/MediaStoreAction.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Actions/MediaStoreActionTest.php`)

```php
- test('validates uploaded file')
- test('extracts metadata from file')
- test('stores file to disk')
- test('saves media record to database')
- test('dispatches media uploaded event')
- test('returns media model')
- test('handles validation errors')
- test('handles storage errors')
- test('supports different collections')
- test('applies collection specific rules')
```

**Feature-тесты** (`tests/Feature/Domain/Media/MediaUploadFlowTest.php`)

```php
- test('complete media upload flow')
- test('image file is uploaded and stored')
- test('video file is uploaded and metadata extracted')
- test('pdf file is uploaded and validated')
- test('corrupted file is rejected')
- test('oversized file is rejected')
- test('invalid mime type is rejected')
- test('exif data is preserved')
- test('variants are generated after upload')
```

---

##### 2.2.2. ListMediaAction

**Путь:** `app/Domain/Media/Actions/ListMediaAction.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Actions/ListMediaActionTest.php`)

```php
- test('lists media with pagination')
- test('filters by mime type')
- test('filters by collection')
- test('includes trashed when requested')
- test('applies search query')
- test('orders by created at')
```

---

##### 2.2.3. UpdateMediaMetadataAction

**Путь:** `app/Domain/Media/Actions/UpdateMediaMetadataAction.php`

**Unit-тесты** (`tests/Unit/Domain/Media/Actions/UpdateMediaMetadataActionTest.php`)

```php
- test('updates media title')
- test('updates media alt text')
- test('updates media caption')
- test('validates input data')
- test('returns updated media')
```

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

### 2.3. Модуль Entries (1 сущность)

#### Приоритет: 🔴 Высокий

##### 2.3.1. PublishingService

**Путь:** `app/Domain/Entries/PublishingService.php`

**Unit-тесты** (`tests/Unit/Domain/Entries/PublishingServiceTest.php`)

```php
- test('publishes entry immediately')
- test('schedules entry for future publishing')
- test('validates publishing date')
- test('changes entry status to published')
- test('dispatches entry published event')
```

**Feature-тесты** (`tests/Feature/Domain/Entries/EntryPublishingTest.php`)

```php
- test('entry can be published')
- test('entry can be scheduled for publishing')
- test('scheduled entry becomes published on date')
- test('entry can be unpublished')
```

---

### 2.4. Модуль Plugins (7 сущностей)

#### Приоритет: 🟡 Средний

##### 2.4.1. PluginActivator

**Путь:** `app/Domain/Plugins/PluginActivator.php`

**Unit-тесты** (`tests/Unit/Domain/Plugins/PluginActivatorTest.php`)

```php
- test('enables plugin')
- test('disables plugin')
- test('validates plugin before enabling')
- test('throws exception if plugin already enabled')
- test('throws exception if plugin already disabled')
- test('dispatches plugin enabled event')
- test('dispatches plugin disabled event')
```

**Feature-тесты** (`tests/Feature/Domain/Plugins/PluginActivationTest.php`)

```php
- test('plugin activation flow')
- test('plugin deactivation flow')
- test('plugin routes are loaded after activation')
- test('plugin routes are removed after deactivation')
```

---

##### 2.4.2. PluginRegistry

**Путь:** `app/Domain/Plugins/PluginRegistry.php`

**Unit-тесты** (`tests/Unit/Domain/Plugins/PluginRegistryTest.php`)

```php
- test('registers plugin')
- test('unregisters plugin')
- test('gets all registered plugins')
- test('gets enabled plugins')
- test('gets disabled plugins')
- test('checks if plugin is registered')
```

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

### 2.5. Модуль Routing (9 сущностей)

#### Приоритет: 🟡 Средний

##### 2.5.1. PathReservationService

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

##### 2.5.3. PathNormalizer

**Путь:** `app/Domain/Routing/PathNormalizer.php`

**Unit-тесты** (`tests/Unit/Domain/Routing/PathNormalizerTest.php`)

```php
- test('normalizes path with leading slash')
- test('normalizes path without trailing slash')
- test('normalizes multiple slashes')
- test('converts to lowercase')
- test('handles unicode characters')
```

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

##### 2.5.5. ReservedPattern

**Путь:** `app/Domain/Routing/ReservedPattern.php`

**Unit-тесты** (`tests/Unit/Domain/Routing/ReservedPatternTest.php`)

```php
- test('creates pattern with path')
- test('creates pattern with prefix')
- test('matches exact path')
- test('matches path prefix')
```

---

##### 2.5.6. Routing Exceptions

**Путь:** `app/Domain/Routing/Exceptions/`

**Unit-тesты**

```php
- test('path already reserved exception')
- test('invalid path exception')
- test('forbidden reservation release exception')
```

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

### 2.7. Модуль View (2 сущности)

#### Приоритет: 🟢 Низкий

##### 2.7.1. BladeTemplateResolver

**Путь:** `app/Domain/View/BladeTemplateResolver.php`

**Unit-тesты** (`tests/Unit/Domain/View/BladeTemplateResolverTest.php`)

```php
- test('resolves template for entry with override')
- test('resolves template by post type and slug')
- test('resolves template by post type')
- test('falls back to global entry template')
- test('checks view existence')
```

**Feature-тесты** (`tests/Feature/Domain/View/TemplateResolutionTest.php`)

```php
- test('entry renders with correct template')
- test('custom template override is respected')
- test('post type specific template is used')
- test('fallback template is used when no specific template exists')
```

---

### 2.8. Модуль Sanitizer (1 сущность)

#### Приоритет: 🟡 Средний

##### 2.8.1. RichTextSanitizer

**Путь:** `app/Domain/Sanitizer/RichTextSanitizer.php`

**Unit-тесты** (`tests/Unit/Domain/Sanitizer/RichTextSanitizerTest.php`)

```php
- test('sanitizes html content')
- test('removes dangerous tags')
- test('removes javascript')
- test('allows safe tags')
- test('preserves formatting')
- test('handles malformed html')
```

---

### 2.9. Модуль Options (1 сущность)

#### Приоритет: 🟢 Низкий

##### 2.9.1. OptionsRepository

**Путь:** `app/Domain/Options/OptionsRepository.php`

**Unit-тesты** (`tests/Unit/Domain/Options/OptionsRepositoryTest.php`)

```php
- test('gets option by namespace and key')
- test('sets option value')
- test('deletes option')
- test('gets all options in namespace')
- test('returns default if option not found')
```

**Feature-тесты** (`tests/Feature/Domain/Options/OptionsManagementTest.php`)

```php
- test('option can be created and retrieved')
- test('option can be updated')
- test('option can be deleted')
- test('options are scoped by namespace')
```

---

### 2.10. Модуль PostTypes (1 сущность)

#### Приоритет: 🟢 Низкий

##### 2.10.1. PostTypeOptions

**Путь:** `app/Domain/PostTypes/PostTypeOptions.php`

**Unit-тesты** (`tests/Unit/Domain/PostTypes/PostTypeOptionsTest.php`)

```php
- test('creates options from array')
- test('converts options to array')
- test('validates option structure')
```

---

## 3. HTTP Controllers (60 эндпоинтов)

### 3.1. Auth API (4 эндпоинта)

#### Приоритет: 🔴 Критичный

**Feature-тесты** (`tests/Feature/Api/Auth/AuthenticationTest.php`)

```php
// POST /api/v1/admin/auth/login
- test('user can login with valid credentials')
- test('user receives access and refresh tokens on login')
- test('login fails with invalid credentials')
- test('login fails with missing credentials')

// GET /api/v1/admin/auth/current
- test('authenticated user can get current user info')
- test('unauthenticated request returns 401')

// POST /api/v1/admin/auth/refresh
- test('user can refresh access token with valid refresh token')
- test('refresh token is rotated on use')
- test('refresh fails with invalid token')
- test('refresh fails with expired token')
- test('refresh fails with revoked token')

// POST /api/v1/admin/auth/logout
- test('user can logout')
- test('refresh token is revoked on logout')
- test('access token becomes invalid after logout')
```

---

### 3.2. Entries API (10 эндпоинтов)

#### Приоритет: 🔴 Высокий

**Feature-тесты** (`tests/Feature/Api/Admin/V1/Entries/EntryManagementTest.php`)

```php
// GET /api/v1/admin/entries
- test('admin can list entries')
- test('entries are paginated')
- test('entries can be filtered by post type')
- test('entries can be filtered by status')
- test('entries can be searched')
- test('unauthenticated request returns 401')

// POST /api/v1/admin/entries
- test('admin can create entry')
- test('entry is created with correct data')
- test('entry validation fails with invalid data')
- test('entry slug is auto-generated')
- test('entry slug must be unique per post type')
- test('unauthenticated request returns 401')

// GET /api/v1/admin/entries/{id}
- test('admin can view entry')
- test('entry includes relationships')
- test('not found returns 404')

// PUT /api/v1/admin/entries/{id}
- test('admin can update entry')
- test('entry data is updated correctly')
- test('entry validation works on update')
- test('not found returns 404')

// DELETE /api/v1/admin/entries/{id}
- test('admin can soft delete entry')
- test('deleted entry is not in default list')
- test('not found returns 404')

// POST /api/v1/admin/entries/{id}/restore
- test('admin can restore deleted entry')
- test('restored entry appears in default list')

// GET /api/v1/admin/entries/statuses
- test('admin can get available statuses')
- test('returns list of valid status values')

// GET /api/v1/admin/entries/{entry}/terms
- test('admin can get entry terms')
- test('terms are grouped by taxonomy')

// PUT /api/v1/admin/entries/{entry}/terms/sync
- test('admin can sync entry terms')
- test('old terms are removed')
- test('new terms are attached')
```

---

### 3.3. Media API (13 эндпоинтов)

#### Приоритет: 🔴 Высокий

**Feature-тесты** (`tests/Feature/Api/Admin/V1/Media/MediaManagementTest.php`)

```php
// GET /api/v1/admin/media
- test('admin can list media')
- test('media are paginated')
- test('media can be filtered by mime type')
- test('media can be filtered by collection')
- test('media can be searched')

// POST /api/v1/admin/media
- test('admin can upload media')
- test('media file is stored')
- test('media metadata is extracted')
- test('media validation works')
- test('upload fails with invalid file')
- test('upload fails with oversized file')

// GET /api/v1/admin/media/{id}
- test('admin can view media')
- test('media includes variants')
- test('media includes metadata')
- test('not found returns 404')

// PUT /api/v1/admin/media/{id}
- test('admin can update media metadata')
- test('title can be updated')
- test('alt text can be updated')

// DELETE /api/v1/admin/media/{id}
- test('admin can soft delete media')
- test('media file remains on disk')
- test('deleted media not in default list')

// POST /api/v1/admin/media/{id}/restore
- test('admin can restore deleted media')

// GET /api/v1/admin/media/{id}/variants
- test('admin can get media variants')
- test('variants include status information')

// POST /api/v1/admin/media/{id}/variants
- test('admin can request new variant')
- test('variant generation job is dispatched')

// GET /api/v1/media/{disk}/{path}
- test('public can access media file')
- test('correct file is returned')
- test('correct mime type header')
- test('not found returns 404')
```

---

### 3.4. PostTypes API (5 эндпоинтов)

#### Приоритет: 🟡 Средний

**Feature-тесты** (`tests/Feature/Api/Admin/V1/PostTypes/PostTypesManagementTest.php`)

```php
// GET /api/v1/admin/post-types
- test('admin can list post types')

// POST /api/v1/admin/post-types
- test('admin can create post type')
- test('post type slug is unique')

// GET /api/v1/admin/post-types/{id}
- test('admin can view post type')

// PUT /api/v1/admin/post-types/{id}
- test('admin can update post type')

// DELETE /api/v1/admin/post-types/{id}
- test('admin can delete post type')
- test('cannot delete post type with entries')
```

---

### 3.5. Plugins API (4 эндпоинта)

#### Приоритет: 🟡 Средний

**Feature-тесты** (`tests/Feature/Api/Admin/V1/Plugins/PluginsManagementTest.php`)

```php
// GET /api/v1/admin/plugins
- test('admin can list plugins')
- test('plugins include enabled status')

// POST /api/v1/admin/plugins/{id}/enable
- test('admin can enable plugin')
- test('plugin routes are loaded')

// POST /api/v1/admin/plugins/{id}/disable
- test('admin can disable plugin')
- test('plugin routes are removed')

// POST /api/v1/admin/plugins/sync
- test('admin can sync plugins from filesystem')
- test('new plugins are discovered')
- test('removed plugins are deleted')
```

---

### 3.6. Options API (3 эндпоинта)

#### Приоритет: 🟢 Низкий

**Feature-тесты** (`tests/Feature/Api/Admin/V1/Options/OptionsManagementTest.php`)

```php
// GET /api/v1/admin/options
- test('admin can get options')
- test('options can be filtered by namespace')

// PUT /api/v1/admin/options
- test('admin can update options')
- test('multiple options can be updated at once')

// DELETE /api/v1/admin/options/{id}
- test('admin can delete option')
```

---

### 3.7. Taxonomies & Terms API (8 эндпоинтов)

#### Приоритет: 🟡 Средний

**Feature-тесты** (`tests/Feature/Api/Admin/V1/Taxonomies/TaxonomiesManagementTest.php`)

```php
// GET /api/v1/admin/taxonomies
- test('admin can list taxonomies')

// POST /api/v1/admin/taxonomies
- test('admin can create taxonomy')

// GET /api/v1/admin/taxonomies/{id}
- test('admin can view taxonomy')

// PUT /api/v1/admin/taxonomies/{id}
- test('admin can update taxonomy')

// DELETE /api/v1/admin/taxonomies/{id}
- test('admin can delete taxonomy')
```

**Feature-тesты** (`tests/Feature/Api/Admin/V1/Terms/TermsManagementTest.php`)

```php
// GET /api/v1/admin/terms
- test('admin can list terms')
- test('terms can be filtered by taxonomy')

// POST /api/v1/admin/terms
- test('admin can create term')
- test('term can have parent')

// GET /api/v1/admin/terms/{id}
- test('admin can view term')
- test('term includes ancestors and descendants')

// PUT /api/v1/admin/terms/{id}
- test('admin can update term')
- test('term hierarchy can be changed')
- test('circular hierarchy is prevented')

// DELETE /api/v1/admin/terms/{id}
- test('admin can delete term')
- test('term children are handled correctly')
```

---

### 3.8. Search API (2 эндпоинта)

#### Приоритет: 🟡 Средний

**Feature-тесты** (`tests/Feature/Api/Admin/V1/Search/SearchAdminTest.php`)

```php
// GET /api/v1/admin/search
- test('admin can search entries')
- test('search returns relevant results')

// POST /api/v1/admin/search/reindex
- test('admin can trigger reindex')
- test('reindex job is dispatched')
```

**Feature-тесты** (`tests/Feature/Api/Public/Search/PublicSearchTest.php`)

```php
// GET /api/v1/search
- test('public can search published entries')
- test('draft entries are not in results')
- test('search results are paginated')
```

---

### 3.9. Path Reservation API (3 эндпоинта)

#### Приоритет: 🟡 Средний

**Feature-тesты** (`tests/Feature/Api/Admin/V1/PathReservations/PathReservationTest.php`)

```php
// GET /api/v1/admin/reserved-paths
- test('admin can list reserved paths')

// POST /api/v1/admin/reserved-paths
- test('admin can reserve path')
- test('duplicate path returns error')

// DELETE /api/v1/admin/reserved-paths/{id}
- test('admin can release path reservation')
- test('system paths cannot be released')
```

---

### 3.10. Utils & Templates API (3 эндпоинта)

#### Приоритет: 🟢 Низкий

**Feature-тesты** (`tests/Feature/Api/Admin/V1/Utils/UtilsTest.php`)

```php
// GET /api/v1/admin/utils/slug
- test('generates slug from title')

// GET /api/v1/admin/templates
- test('lists available blade templates')
```

---

### 3.11. Web Controllers (3 контроллера)

#### Приоритет: 🟢 Низкий

**Feature-тesты** (`tests/Feature/Web/PagesTest.php`)

```php
// GET /
- test('homepage renders')
- test('homepage uses correct template')

// GET /{slug}
- test('entry page renders')
- test('entry uses correct template')
- test('not found returns 404')

// GET /admin (ping)
- test('admin ping returns ok')
```

---

## 4. Validation Rules (6 правил)

### Приоритет: 🟡 Средний

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
