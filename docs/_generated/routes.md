# Routes

> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:routes` to update.

_Last generated: 2025-11-10 05:11:35_

## Web

| Method | URI | Name | Action | Middleware |
|--------|-----|------|--------|------------|
| GET|HEAD | `up` | - | _Closure_ | - |
| GET|HEAD | `/` | home | `App\Http\Controllers\HomeController` | web |
| GET|HEAD | `{slug}` | page.show | `PageController@show` | web, App\Http\Middleware\RejectReservedIfMatched |
| GET|HEAD | `{fallbackPlaceholder}` | - | `App\Http\Controllers\FallbackController` | - |
| POST|PUT|PATCH|DELETE|OPTIONS | `{any?}` | - | `App\Http\Controllers\FallbackController` | - |
| GET|HEAD | `storage/{path}` | storage.local | _Closure_ | - |

## Public API

| Method | URI | Name | Action | Middleware |
|--------|-----|------|--------|------------|
| POST | `api/v1/auth/login` | api.auth.login | `LoginController@login` | api, throttle:login, no-cache-auth |
| POST | `api/v1/auth/refresh` | api.auth.refresh | `RefreshController@refresh` | api, throttle:refresh, no-cache-auth |
| POST | `api/v1/auth/logout` | api.auth.logout | `LogoutController@logout` | api, jwt.auth, throttle:login, no-cache-auth |
| GET|HEAD | `api/v1/search` | api.v1.search | `SearchController@index` | api, throttle:search-public |
| GET|HEAD | `api/v1/admin/auth/current` | admin.v1.auth.current | `CurrentUserController@show` | api, jwt.auth, throttle:api, no-cache-auth |
| GET|HEAD | `api/v1/admin/utils/slugify` | - | `UtilsController@slugify` | api, jwt.auth, throttle:api |
| GET|HEAD | `api/v1/admin/plugins` | admin.v1.plugins.index | `PluginsController@index` | api, jwt.auth, throttle:api, can:plugins.read, throttle:60,1 |
| POST | `api/v1/admin/plugins/sync` | admin.v1.plugins.sync | `PluginsController@sync` | api, jwt.auth, throttle:api, can:plugins.sync, throttle:10,1 |
| POST | `api/v1/admin/plugins/{slug}/enable` | admin.v1.plugins.enable | `PluginsController@enable` | api, jwt.auth, throttle:api, can:plugins.toggle, throttle:10,1 |
| POST | `api/v1/admin/plugins/{slug}/disable` | admin.v1.plugins.disable | `PluginsController@disable` | api, jwt.auth, throttle:api, can:plugins.toggle, throttle:10,1 |
| GET|HEAD | `api/v1/admin/reservations` | - | `PathReservationController@index` | api, jwt.auth, throttle:api, can:viewAny,App\Models\ReservedRoute |
| POST | `api/v1/admin/reservations` | - | `PathReservationController@store` | api, jwt.auth, throttle:api, can:create,App\Models\ReservedRoute |
| DELETE | `api/v1/admin/reservations/{path}` | - | `PathReservationController@destroy` | api, jwt.auth, throttle:api, can:deleteAny,App\Models\ReservedRoute |
| GET|HEAD | `api/v1/admin/post-types/{slug}` | admin.v1.post-types.show | `PostTypeController@show` | api, jwt.auth, throttle:api, App\Http\Middleware\EnsureCanManagePostTypes |
| PUT | `api/v1/admin/post-types/{slug}` | admin.v1.post-types.update | `PostTypeController@update` | api, jwt.auth, throttle:api, App\Http\Middleware\EnsureCanManagePostTypes |
| GET|HEAD | `api/v1/admin/entries` | admin.v1.entries.index | `EntryController@index` | api, jwt.auth, throttle:api, can:viewAny,App\Models\Entry |
| POST | `api/v1/admin/entries` | admin.v1.entries.store | `EntryController@store` | api, jwt.auth, throttle:api, can:create,App\Models\Entry |
| GET|HEAD | `api/v1/admin/entries/{id}` | admin.v1.entries.show | `EntryController@show` | api, jwt.auth, throttle:api |
| PUT | `api/v1/admin/entries/{id}` | admin.v1.entries.update | `EntryController@update` | api, jwt.auth, throttle:api |
| DELETE | `api/v1/admin/entries/{id}` | admin.v1.entries.destroy | `EntryController@destroy` | api, jwt.auth, throttle:api |
| POST | `api/v1/admin/entries/{id}/restore` | admin.v1.entries.restore | `EntryController@restore` | api, jwt.auth, throttle:api |
| GET|HEAD | `api/v1/admin/taxonomies` | admin.v1.taxonomies.index | `TaxonomyController@index` | api, jwt.auth, throttle:api, can:manage.taxonomies |
| POST | `api/v1/admin/taxonomies` | admin.v1.taxonomies.store | `TaxonomyController@store` | api, jwt.auth, throttle:api, can:manage.taxonomies |
| GET|HEAD | `api/v1/admin/taxonomies/{slug}` | admin.v1.taxonomies.show | `TaxonomyController@show` | api, jwt.auth, throttle:api, can:manage.taxonomies |
| PUT | `api/v1/admin/taxonomies/{slug}` | admin.v1.taxonomies.update | `TaxonomyController@update` | api, jwt.auth, throttle:api, can:manage.taxonomies |
| DELETE | `api/v1/admin/taxonomies/{slug}` | admin.v1.taxonomies.destroy | `TaxonomyController@destroy` | api, jwt.auth, throttle:api, can:manage.taxonomies |
| GET|HEAD | `api/v1/admin/taxonomies/{taxonomy}/terms` | admin.v1.taxonomies.terms.index | `TermController@indexByTaxonomy` | api, jwt.auth, throttle:api, can:manage.terms |
| POST | `api/v1/admin/taxonomies/{taxonomy}/terms` | admin.v1.taxonomies.terms.store | `TermController@store` | api, jwt.auth, throttle:api, can:manage.terms |
| GET|HEAD | `api/v1/admin/terms/{term}` | admin.v1.terms.show | `TermController@show` | api, jwt.auth, throttle:api, can:manage.terms |
| PUT | `api/v1/admin/terms/{term}` | admin.v1.terms.update | `TermController@update` | api, jwt.auth, throttle:api, can:manage.terms |
| DELETE | `api/v1/admin/terms/{term}` | admin.v1.terms.destroy | `TermController@destroy` | api, jwt.auth, throttle:api, can:manage.terms |
| GET|HEAD | `api/v1/admin/entries/{entry}/terms` | admin.v1.entries.terms.index | `EntryTermsController@index` | api, jwt.auth, throttle:api, can:manage.terms |
| POST | `api/v1/admin/entries/{entry}/terms/attach` | admin.v1.entries.terms.attach | `EntryTermsController@attach` | api, jwt.auth, throttle:api, can:manage.terms |
| POST | `api/v1/admin/entries/{entry}/terms/detach` | admin.v1.entries.terms.detach | `EntryTermsController@detach` | api, jwt.auth, throttle:api, can:manage.terms |
| PUT | `api/v1/admin/entries/{entry}/terms/sync` | admin.v1.entries.terms.sync | `EntryTermsController@sync` | api, jwt.auth, throttle:api, can:manage.terms |
| GET|HEAD | `api/v1/admin/media` | admin.v1.media.index | `MediaController@index` | api, jwt.auth, throttle:api, can:viewAny,App\Models\Media, throttle:60,1 |
| GET|HEAD | `api/v1/admin/media/{media}` | admin.v1.media.show | `MediaController@show` | api, jwt.auth, throttle:api, can:viewAny,App\Models\Media, throttle:60,1 |
| GET|HEAD | `api/v1/admin/media/{media}/preview` | admin.v1.media.preview | `MediaPreviewController@preview` | api, jwt.auth, throttle:api, can:viewAny,App\Models\Media, throttle:60,1 |
| GET|HEAD | `api/v1/admin/media/{media}/download` | admin.v1.media.download | `MediaPreviewController@download` | api, jwt.auth, throttle:api, can:viewAny,App\Models\Media, throttle:60,1 |
| POST | `api/v1/admin/media` | admin.v1.media.store | `MediaController@store` | api, jwt.auth, throttle:api, can:create,App\Models\Media, throttle:20,1 |
| PUT | `api/v1/admin/media/{media}` | admin.v1.media.update | `MediaController@update` | api, jwt.auth, throttle:api, throttle:20,1 |
| DELETE | `api/v1/admin/media/{media}` | admin.v1.media.destroy | `MediaController@destroy` | api, jwt.auth, throttle:api, throttle:20,1 |
| POST | `api/v1/admin/media/{media}/restore` | admin.v1.media.restore | `MediaController@restore` | api, jwt.auth, throttle:api, throttle:20,1 |
| GET|HEAD | `api/v1/admin/options/{namespace}` | admin.v1.options.index | `OptionsController@index` | api, jwt.auth, throttle:api, can:viewAny,App\Models\Option, can:options.read, throttle:120,1 |
| GET|HEAD | `api/v1/admin/options/{namespace}/{key}` | admin.v1.options.show | `OptionsController@show` | api, jwt.auth, throttle:api, can:options.read, throttle:120,1 |
| PUT | `api/v1/admin/options/{namespace}/{key}` | admin.v1.options.upsert | `OptionsController@put` | api, jwt.auth, throttle:api, can:options.write, throttle:30,1 |
| DELETE | `api/v1/admin/options/{namespace}/{key}` | admin.v1.options.destroy | `OptionsController@destroy` | api, jwt.auth, throttle:api, can:options.delete, throttle:30,1 |
| POST | `api/v1/admin/options/{namespace}/{key}/restore` | admin.v1.options.restore | `OptionsController@restore` | api, jwt.auth, throttle:api, can:options.restore, throttle:30,1 |
| POST | `api/v1/admin/search/reindex` | admin.v1.search.reindex | `SearchAdminController@reindex` | api, jwt.auth, throttle:api, can:search.reindex, throttle:search-reindex |

