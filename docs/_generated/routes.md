# Routes

> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:routes` to update.

_Last generated: 2025-11-08 09:12:41_

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
| POST | `api/v1/auth/logout` | - | `LogoutController@logout` | api, throttle:login, no-cache-auth |
| GET|HEAD | `api/v1/auth/csrf` | - | `CsrfController@issue` | api, no-cache-auth |
| GET|HEAD | `api/v1/admin/utils/slugify` | - | `UtilsController@slugify` | api, admin.auth, throttle:api |
| GET|HEAD | `api/v1/admin/reservations` | - | `PathReservationController@index` | api, admin.auth, throttle:api, can:viewAny,App\Models\ReservedRoute |
| POST | `api/v1/admin/reservations` | - | `PathReservationController@store` | api, admin.auth, throttle:api, can:create,App\Models\ReservedRoute |
| DELETE | `api/v1/admin/reservations/{path}` | - | `PathReservationController@destroy` | api, admin.auth, throttle:api, can:deleteAny,App\Models\ReservedRoute |
| GET|HEAD | `api/v1/admin/post-types/{slug}` | admin.v1.post-types.show | `PostTypeController@show` | api, admin.auth, throttle:api, App\Http\Middleware\EnsureCanManagePostTypes |
| PUT | `api/v1/admin/post-types/{slug}` | admin.v1.post-types.update | `PostTypeController@update` | api, admin.auth, throttle:api, App\Http\Middleware\EnsureCanManagePostTypes |
| GET|HEAD | `api/v1/admin/entries` | admin.v1.entries.index | `EntryController@index` | api, admin.auth, throttle:api, can:viewAny,App\Models\Entry |
| POST | `api/v1/admin/entries` | admin.v1.entries.store | `EntryController@store` | api, admin.auth, throttle:api, can:create,App\Models\Entry |
| GET|HEAD | `api/v1/admin/entries/{id}` | admin.v1.entries.show | `EntryController@show` | api, admin.auth, throttle:api |
| PUT | `api/v1/admin/entries/{id}` | admin.v1.entries.update | `EntryController@update` | api, admin.auth, throttle:api |
| DELETE | `api/v1/admin/entries/{id}` | admin.v1.entries.destroy | `EntryController@destroy` | api, admin.auth, throttle:api |
| POST | `api/v1/admin/entries/{id}/restore` | admin.v1.entries.restore | `EntryController@restore` | api, admin.auth, throttle:api |

