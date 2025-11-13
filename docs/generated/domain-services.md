# Domain Services

## BladeTemplateResolver
**ID:** `domain_service:View/BladeTemplateResolver`
**Path:** `app/Domain/View/BladeTemplateResolver.php`

Резолвер для выбора Blade-шаблона по файловой конвенции.

### Details
Приоритет:
1. Entry.template_override (если задано — используется как полное имя вью)
2. entry--{postType}--{slug} (если существует)
3. entry--{postType} (если существует)
4. entry (глобальный)

### Meta
- **Methods:** `forEntry`
- **Interface:** `App\Domain\View\TemplateResolver`

### Tags
`view`


---

## DefaultEntrySlugService
**ID:** `domain_service:Entries/DefaultEntrySlugService`
**Path:** `app/Domain/Entries/DefaultEntrySlugService.php`

DefaultEntrySlugService

### Meta
- **Methods:** `onCreated`, `onUpdated`, `currentSlug`
- **Interface:** `App\Domain\Entries\EntrySlugService`

### Tags
`entry`


---

## ElasticsearchSearchClient
**ID:** `domain_service:Search/Clients/ElasticsearchSearchClient`
**Path:** `app/Domain/Search/Clients/ElasticsearchSearchClient.php`

ElasticsearchSearchClient

### Meta
- **Methods:** `search`, `createIndex`, `deleteIndex`, `updateAliases`, `getIndicesForAlias`, `bulk`, `refresh`
- **Dependencies:** `Illuminate\Http\Client\Factory`
- **Interface:** `App\Domain\Search\SearchClientInterface`

### Tags
`search`, `client`


---

## EntryToSearchDoc
**ID:** `domain_service:Search/Transformers/EntryToSearchDoc`
**Path:** `app/Domain/Search/Transformers/EntryToSearchDoc.php`

EntryToSearchDoc

### Meta
- **Methods:** `transform`

### Tags
`search`, `transformer`


---

## GenerateVariantJob
**ID:** `domain_service:Media/Jobs/GenerateVariantJob`
**Path:** `app/Domain/Media/Jobs/GenerateVariantJob.php`

GenerateVariantJob

### Meta
- **Methods:** `handle`, `dispatch`, `dispatchIf`, `dispatchUnless`, `dispatchSync`, `dispatchAfterResponse`, `withChain`, `attempts`, `delete`, `fail`, `release`, `withFakeQueueInteractions`, `assertDeleted`, `assertNotDeleted`, `assertFailed`, `assertFailedWith`, `assertNotFailed`, `assertReleased`, `assertNotReleased`, `setJob`, `onConnection`, `onQueue`, `onGroup`, `withDeduplicator`, `allOnConnection`, `allOnQueue`, `delay`, `withoutDelay`, `afterCommit`, `beforeCommit`, `through`, `chain`, `prependToChain`, `appendToChain`, `dispatchNextJobInChain`, `invokeChainCatchCallbacks`, `assertHasChain`, `assertDoesntHaveChain`, `restoreModel`
- **Interface:** `Illuminate\Contracts\Queue\ShouldQueue`

### Tags
`media`, `job`


---

## IndexManager
**ID:** `domain_service:Search/IndexManager`
**Path:** `app/Domain/Search/IndexManager.php`

IndexManager

### Meta
- **Methods:** `reindex`
- **Dependencies:** `App\Domain\Search\SearchClientInterface`, `App\Domain\Search\Transformers\EntryToSearchDoc`

### Tags
`search`


---

## JwtService
**ID:** `domain_service:Auth/JwtService`
**Path:** `app/Domain/Auth/JwtService.php`

Service for issuing and verifying JWT access and refresh tokens.

### Details
Uses HS256 (HMAC with SHA-256) algorithm with a secret key.

### Meta
- **Methods:** `issueAccessToken`, `issueRefreshToken`, `encode`, `verify`

### Tags
`auth`


---

## MediaMetadataExtractor
**ID:** `domain_service:Media/Services/MediaMetadataExtractor`
**Path:** `app/Domain/Media/Services/MediaMetadataExtractor.php`

MediaMetadataExtractor

### Meta
- **Methods:** `extract`

### Tags
`media`, `service`


---

## MediaStoreAction
**ID:** `domain_service:Media/Actions/MediaStoreAction`
**Path:** `app/Domain/Media/Actions/MediaStoreAction.php`

MediaStoreAction

### Meta
- **Methods:** `execute`
- **Dependencies:** `App\Domain\Media\Services\MediaMetadataExtractor`

### Tags
`media`, `action`


---

## NotReservedRoute
**ID:** `domain_service:Pages/Validation/NotReservedRoute`
**Path:** `app/Domain/Pages/Validation/NotReservedRoute.php`

NotReservedRoute

### Meta
- **Methods:** `passes`, `message`
- **Dependencies:** `App\Domain\Routing\ReservedRouteRegistry`
- **Interface:** `Illuminate\Contracts\Validation\Rule`

### Tags
`page`, `validation`


---

## NullSearchClient
**ID:** `domain_service:Search/Clients/NullSearchClient`
**Path:** `app/Domain/Search/Clients/NullSearchClient.php`

NullSearchClient

### Meta
- **Methods:** `search`, `createIndex`, `deleteIndex`, `updateAliases`, `getIndicesForAlias`, `bulk`, `refresh`
- **Interface:** `App\Domain\Search\SearchClientInterface`

### Tags
`search`, `client`


---

## OnDemandVariantService
**ID:** `domain_service:Media/Services/OnDemandVariantService`
**Path:** `app/Domain/Media/Services/OnDemandVariantService.php`

OnDemandVariantService

### Meta
- **Methods:** `ensureVariant`, `generateVariant`

### Tags
`media`, `service`


---

## OptionsRepository
**ID:** `domain_service:Options/OptionsRepository`
**Path:** `app/Domain/Options/OptionsRepository.php`

OptionsRepository

### Meta
- **Methods:** `get`, `set`, `delete`, `restore`, `getInt`
- **Dependencies:** `Illuminate\Contracts\Cache\Repository`

### Tags
`option`


---

## PathNormalizer
**ID:** `domain_service:Routing/PathNormalizer`
**Path:** `app/Domain/Routing/PathNormalizer.php`

PathNormalizer

### Meta
- **Methods:** `normalize`

### Tags
`routing`


---

## PathReservationServiceImpl
**ID:** `domain_service:Routing/PathReservationServiceImpl`
**Path:** `app/Domain/Routing/PathReservationServiceImpl.php`

PathReservationServiceImpl

### Meta
- **Methods:** `reservePath`, `releasePath`, `releaseBySource`, `isReserved`, `ownerOf`
- **Dependencies:** `App\Domain\Routing\PathReservationStore`
- **Interface:** `App\Domain\Routing\PathReservationService`

### Tags
`routing`


---

## PathReservationStoreImpl
**ID:** `domain_service:Routing/PathReservationStoreImpl`
**Path:** `app/Domain/Routing/PathReservationStoreImpl.php`

PathReservationStoreImpl

### Meta
- **Methods:** `insert`, `delete`, `deleteIfOwnedBy`, `deleteBySource`, `exists`, `ownerOf`, `getAllPaths`, `isUniqueViolation`
- **Interface:** `App\Domain\Routing\PathReservationStore`

### Tags
`routing`


---

## PluginActivator
**ID:** `domain_service:Plugins/PluginActivator`
**Path:** `app/Domain/Plugins/PluginActivator.php`

PluginActivator

### Meta
- **Methods:** `enable`, `disable`
- **Dependencies:** `App\Domain\Plugins\Services\PluginsRouteReloader`

### Tags
`plugin`


---

## PluginAutoloader
**ID:** `domain_service:Plugins/Services/PluginAutoloader`
**Path:** `app/Domain/Plugins/Services/PluginAutoloader.php`

PluginAutoloader

### Meta
- **Methods:** `register`

### Tags
`plugin`, `service`


---

## PluginDisabled
**ID:** `domain_service:Plugins/Events/PluginDisabled`
**Path:** `app/Domain/Plugins/Events/PluginDisabled.php`

PluginDisabled

### Meta
- **Methods:** `dispatch`, `dispatchIf`, `dispatchUnless`, `broadcast`, `restoreModel`
- **Dependencies:** `App\Models\Plugin`

### Tags
`plugin`, `event`


---

## PluginEnabled
**ID:** `domain_service:Plugins/Events/PluginEnabled`
**Path:** `app/Domain/Plugins/Events/PluginEnabled.php`

PluginEnabled

### Meta
- **Methods:** `dispatch`, `dispatchIf`, `dispatchUnless`, `broadcast`, `restoreModel`
- **Dependencies:** `App\Models\Plugin`

### Tags
`plugin`, `event`


---

## PluginRegistry
**ID:** `domain_service:Plugins/PluginRegistry`
**Path:** `app/Domain/Plugins/PluginRegistry.php`

PluginRegistry

### Meta
- **Methods:** `enabled`, `enabledProviders`

### Tags
`plugin`


---

## PluginsRouteReloader
**ID:** `domain_service:Plugins/Services/PluginsRouteReloader`
**Path:** `app/Domain/Plugins/Services/PluginsRouteReloader.php`

PluginsRouteReloader

### Meta
- **Methods:** `reload`
- **Dependencies:** `Illuminate\Contracts\Foundation\Application`, `App\Domain\Plugins\PluginRegistry`, `App\Domain\Plugins\Services\PluginAutoloader`

### Tags
`plugin`, `service`


---

## PluginsRoutesReloaded
**ID:** `domain_service:Plugins/Events/PluginsRoutesReloaded`
**Path:** `app/Domain/Plugins/Events/PluginsRoutesReloaded.php`

PluginsRoutesReloaded

### Meta
- **Methods:** `dispatch`, `dispatchIf`, `dispatchUnless`, `broadcast`, `restoreModel`

### Tags
`plugin`, `event`


---

## PluginsSyncCommand
**ID:** `domain_service:Plugins/Commands/PluginsSyncCommand`
**Path:** `app/Domain/Plugins/Commands/PluginsSyncCommand.php`

PluginsSyncCommand

### Meta
- **Methods:** `handle`
- **Dependencies:** `App\Domain\Plugins\Services\PluginsSynchronizer`
- **Interface:** `Symfony\Component\Console\Command\SignalableCommandInterface`

### Tags
`plugin`, `command`


---

## PluginsSynced
**ID:** `domain_service:Plugins/Events/PluginsSynced`
**Path:** `app/Domain/Plugins/Events/PluginsSynced.php`

PluginsSynced

### Meta
- **Methods:** `dispatch`, `dispatchIf`, `dispatchUnless`, `broadcast`, `restoreModel`

### Tags
`plugin`, `event`


---

## PluginsSynchronizer
**ID:** `domain_service:Plugins/Services/PluginsSynchronizer`
**Path:** `app/Domain/Plugins/Services/PluginsSynchronizer.php`

PluginsSynchronizer

### Meta
- **Methods:** `sync`
- **Dependencies:** `App\Domain\Plugins\Services\PluginsRouteReloader`

### Tags
`plugin`, `service`


---

## PublishingService
**ID:** `domain_service:Entries/PublishingService`
**Path:** `app/Domain/Entries/PublishingService.php`

PublishingService

### Meta
- **Methods:** `applyAndValidate`

### Tags
`entry`


---

## RefreshTokenDto
**ID:** `domain_service:Auth/RefreshTokenDto`
**Path:** `app/Domain/Auth/RefreshTokenDto.php`

Data Transfer Object for RefreshToken.

### Details
Provides type-safe access to refresh token data without exposing Eloquent model.

### Meta
- **Methods:** `isValid`, `isInvalid`
- **Dependencies:** `Carbon\Carbon`, `Carbon\Carbon`, `Carbon\Carbon`, `Carbon\Carbon`, `Carbon\Carbon`

### Tags
`auth`


---

## RefreshTokenRepositoryImpl
**ID:** `domain_service:Auth/RefreshTokenRepositoryImpl`
**Path:** `app/Domain/Auth/RefreshTokenRepositoryImpl.php`

Implementation of RefreshTokenRepository using Eloquent.

### Meta
- **Methods:** `store`, `markUsedConditionally`, `revoke`, `revokeFamily`, `find`, `deleteExpired`
- **Interface:** `App\Domain\Auth\RefreshTokenRepository`

### Tags
`auth`


---

## ReindexSearchJob
**ID:** `domain_service:Search/Jobs/ReindexSearchJob`
**Path:** `app/Domain/Search/Jobs/ReindexSearchJob.php`

ReindexSearchJob

### Meta
- **Methods:** `handle`, `dispatch`, `dispatchIf`, `dispatchUnless`, `dispatchSync`, `dispatchAfterResponse`, `withChain`, `attempts`, `delete`, `fail`, `release`, `withFakeQueueInteractions`, `assertDeleted`, `assertNotDeleted`, `assertFailed`, `assertFailedWith`, `assertNotFailed`, `assertReleased`, `assertNotReleased`, `setJob`, `onConnection`, `onQueue`, `onGroup`, `withDeduplicator`, `allOnConnection`, `allOnQueue`, `delay`, `withoutDelay`, `afterCommit`, `beforeCommit`, `through`, `chain`, `prependToChain`, `appendToChain`, `dispatchNextJobInChain`, `invokeChainCatchCallbacks`, `assertHasChain`, `assertDoesntHaveChain`, `restoreModel`
- **Interface:** `Illuminate\Contracts\Queue\ShouldQueue`

### Tags
`search`, `job`


---

## ReservedPattern
**ID:** `domain_service:Routing/ReservedPattern`
**Path:** `app/Domain/Routing/ReservedPattern.php`

Генератор регулярного выражения для плоских URL с исключением зарезервированных путей.

### Details
Используется для создания негативного lookahead паттерна, который исключает
зарезервированные первые сегменты из плоской маршрутизации /{slug}.
При route:cache список фиксируется до следующего деплоя/инвалидации.
Это приемлемо, так как сами плагины/система регистрируют свои конкретные роуты
раньше и перехватят свои пути.

### Meta
- **Methods:** `slugRegex`

### Tags
`routing`


---

## ReservedRouteRegistry
**ID:** `domain_service:Routing/ReservedRouteRegistry`
**Path:** `app/Domain/Routing/ReservedRouteRegistry.php`

ReservedRouteRegistry

### Meta
- **Methods:** `all`, `isReservedPath`, `isReservedPrefix`, `isReservedSlug`, `clearCache`
- **Dependencies:** `Illuminate\Contracts\Cache\Repository`

### Tags
`routing`


---

## RichTextSanitizer
**ID:** `domain_service:Sanitizer/RichTextSanitizer`
**Path:** `app/Domain/Sanitizer/RichTextSanitizer.php`

RichTextSanitizer

### Meta
- **Methods:** `sanitize`

### Tags
`sanitizer`


---

## SearchHit
**ID:** `domain_service:Search/SearchHit`
**Path:** `app/Domain/Search/SearchHit.php`

SearchHit

### Meta


### Tags
`search`


---

## SearchQuery
**ID:** `domain_service:Search/SearchQuery`
**Path:** `app/Domain/Search/SearchQuery.php`

SearchQuery

### Meta
- **Methods:** `query`, `postTypes`, `terms`, `from`, `to`, `page`, `perPage`, `offset`, `isBlank`
- **Dependencies:** `Carbon\CarbonImmutable`, `Carbon\CarbonImmutable`

### Tags
`search`


---

## SearchReindexCommand
**ID:** `domain_service:Search/Commands/SearchReindexCommand`
**Path:** `app/Domain/Search/Commands/SearchReindexCommand.php`

SearchReindexCommand

### Meta
- **Methods:** `handle`
- **Interface:** `Symfony\Component\Console\Command\SignalableCommandInterface`

### Tags
`search`, `command`


---

## SearchResult
**ID:** `domain_service:Search/SearchResult`
**Path:** `app/Domain/Search/SearchResult.php`

SearchResult

### Meta
- **Methods:** `hits`, `total`, `page`, `perPage`, `tookMs`, `empty`

### Tags
`search`


---

## SearchService
**ID:** `domain_service:Search/SearchService`
**Path:** `app/Domain/Search/SearchService.php`

SearchService

### Meta
- **Methods:** `search`
- **Dependencies:** `App\Domain\Search\SearchClientInterface`, `App\Support\Errors\ErrorFactory`

### Tags
`search`


---

## SearchTermFilter
**ID:** `domain_service:Search/ValueObjects/SearchTermFilter`
**Path:** `app/Domain/Search/ValueObjects/SearchTermFilter.php`

SearchTermFilter

### Meta
- **Methods:** `fromString`

### Tags
`search`, `valueobject`


---
