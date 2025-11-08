# T-046 — Admin API: PostTypes (read/update)

```yaml
id: T-046
title: Эндпоинты чтения/обновления PostType.options_json (без create/delete)
area: [backend, laravel, api, admin]
priority: P1
size: S
depends_on: []
blocks: []
labels: [stupidCms, mvp, admin-api, posttypes]
```

## 1) Контекст

Админке нужно читать/обновлять конфигурацию типа записи (`PostType`) — поле `options_json`.  
Никакого создания/удаления типов через этот API; задача только про **GET/PUT** конкретного типа по слагу (например, `page`).  
Ошибки возвращаем по RFC7807. Авторизация — только администратор/редактор с правом `manage.posttypes`.

## 2) Требуемый результат (Deliverables)

- **Код:**
  - `app/Models/PostType.php` — убедиться, что модель существует; добавить `$casts['options_json'] = 'array'`.
  - `app/Http/Controllers/Admin/V1/PostTypeController.php` — методы `show(string $slug)` и `update(UpdatePostTypeRequest $request, string $slug)`.
  - `app/Http/Requests/Admin/UpdatePostTypeRequest.php` — валидация входа (см. ниже).
  - `app/Http/Resources/Admin/PostTypeResource.php` — единый формат отдачи.
  - `routes/api.php` — маршруты `/api/v1/admin/post-types/{slug}` (GET, PUT) с префиксами/мидлварами.
  - (Опц.) `app/Policies/PostTypePolicy.php` — правило `update`/`view`; привязать в `AuthServiceProvider` или использовать `Gate::authorize('manage.posttypes')`.
- **Тесты:**
  - `tests/Feature/Admin/PostTypes/ShowPostTypeTest.php` — успешный GET, 404 при неизвестном слаге, 401/403 без прав.
  - `tests/Feature/Admin/PostTypes/UpdatePostTypeTest.php` — успешный PUT, 422 при невалидном `options_json`, запрет изменения служебных полей.
- **Документация:**
  - `docs/admin-api/post-types.md` — описание эндпоинтов, схемы, примеры `curl`.
- **Команды проверки:**
  - `phpunit --testsuite Feature --filter PostTypes`
  - `curl` примеры см. ниже.

## 3) Функциональные требования

- Эндпоинты:
  - `GET  /api/v1/admin/post-types/{slug}` — отдать PostType.
  - `PUT  /api/v1/admin/post-types/{slug}` — принять объект с ключом `options_json` и обновить.
- `slug` — путь-переменная (строка в нижнем регистре, [a-z0-9_-]).
- Доступ: только аутентифицированные с правом `manage.posttypes`. Иначе — `401/403`.
- `options_json`:
  - Допускается **только объект** (ассоц. массив). Пустой объект `{}` — допустим.
  - Вложенность без ограничений; значения — скаляры/объекты/массивы.
  - Никаких побочных эффектов: обновляется **только** `options_json`. `slug`, `name`, `icon` и т.п. неизменяемы.
- Блокировки операций:
  - **Нет** `POST /.../post-types` (создание) и `DELETE /.../post-types/{slug}` (удаление).
  - Для системного типа `page` — это же правило: только GET/PUT.
- Ответы кэшировать **нельзя** (админ): `Cache-Control: private, no-store` + `Vary: Cookie`.
- Формат ошибок — RFC7807 (`application/problem+json`).

## 4) Нефункциональные требования

- Laravel 12.x, PHP 8.2+; без сторонних пакетов.
- Валидация и санитайзинг: отклонять не-объект (массив верхнего уровня, строка и т.п.) для `options_json`.
- Транзакционность: обновление в транзакции, с перезагрузкой модели после `save`.
- Наблюдаемость: логировать факт изменения (`audit` на уровне info/debug по желанию).

## 5) Контракты API

### 5.1 Схема ответа (Resource)

`application/json`:

```json
{
  "data": {
    "slug": "page",
    "label": "Страница",
    "options_json": {
      "template": "default",
      "editor": {"toolbar": ["h2", "bold", "link"]}
    },
    "updated_at": "2025-11-08T12:00:00Z"
  }
}
```

### 5.2 Запрос на обновление

`PUT /api/v1/admin/post-types/page`  
`Content-Type: application/json`

```json
{
  "options_json": {
    "template": "landing",
    "seo": { "noindex": false }
  }
}
```

Ответ `200 OK` с обновлённым объектом (как в 5.1).

### 5.3 Ошибки (RFC7807)

- `401` (unauthenticated) / `403` (forbidden)
- `404` (unknown slug):  
```json
{
  "type":"https://stupidcms.dev/problems/not-found",
  "title":"PostType not found",
  "status":404,
  "detail":"Unknown post type slug: pagex",
  "instance":"/api/v1/admin/post-types/pagex"
}
```
- `422` (invalid payload):
```json
{
  "type":"https://stupidcms.dev/problems/validation-error",
  "title":"Validation error",
  "status":422,
  "detail":"The options_json field must be an object.",
  "errors":{"options_json":["The options_json field must be an object."]}
}
```

## 6) Схема БД (допущение)

Таблица `post_types` уже существует и содержит как минимум: `id`, `slug` (UNIQUE), `label`, `options_json` (JSON), `created_at`, `updated_at`.  
Миграций в задаче **нет**. В `PostType` добавить `$fillable = ['options_json']` (или явное присвоение).

## 7) План реализации (для ИИ)

1. В `PostType` добавить `$casts['options_json']='array'` и (по необходимости) `$fillable`.
2. Создать `PostTypeResource` c нужной формой.
3. Создать `UpdatePostTypeRequest` с правилами: `required`, `array`, `present`, `not list` (кастом-валидатор см. ниже).
4. Реализовать `PostTypeController@show` и `@update`:
   - найти по `slug` или 404 (RFC7807);
   - в `update` — транзакция, присвоение `options_json`, `save()`, `refresh()`.
5. Добавить маршруты с префиксом `api/v1/admin`, middleware `auth:api`/`can:manage.posttypes`, `throttle:api`.
6. Установить заголовки `Cache-Control: private, no-store` и `Vary: Cookie` в ответах контроллера или через middleware.
7. Написать feature-тесты на GET/PUT/422/404/403.
8. Обновить документацию `docs/admin-api/post-types.md` с примерами `curl`.

## 8) Acceptance Criteria

- [ ] `GET /api/v1/admin/post-types/page` возвращает `200` и объект PostType с `options_json`.
- [ ] `PUT /api/v1/admin/post-types/page` обновляет **только** `options_json` и возвращает `200` с обновлёнными данными.
- [ ] Неверный `options_json` (не объект) → `422` с RFC7807-ошибкой.
- [ ] Неизвестный `slug` → `404` RFC7807.
- [ ] Без прав/неавторизован → `401/403`.
- [ ] Админ-ответы помечены `Cache-Control: private, no-store` и `Vary: Cookie`.
- [ ] Все тесты зелёные.

## 9) Роллаут / Бэкаут

**Роллаут:** деплой → `php artisan route:cache` → smoke-тест `curl`.  
**Бэкаут:** откат контроллера/роутов; фича не изменяет схему БД.

## 10) Формат ответа от нейросети (для реализации)

Вернуть: **Plan / Files / Patchset / Tests / Checks / Notes** — как в проектном шаблоне.

---

## 11) Эскизы кода

### 11.1 Роутинг

```php
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\V1\PostTypeController;

Route::prefix('api/v1/admin')
    ->middleware(['auth:api', 'throttle:api'])
    ->group(function () {
        Route::get('post-types/{slug}', [PostTypeController::class, 'show'])
            ->middleware('can:manage.posttypes')
            ->name('admin.v1.post-types.show');

        Route::put('post-types/{slug}', [PostTypeController::class, 'update'])
            ->middleware('can:manage.posttypes')
            ->name('admin.v1.post-types.update');
    });
```

### 11.2 Контроллер

```php
<?php
namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePostTypeRequest;
use App\Http\Resources\Admin\PostTypeResource;
use App\Models\PostType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PostTypeController extends Controller
{
    public function show(string $slug): Response
    {
        $type = PostType::query()->where('slug', $slug)->first();

        if (!$type) {
            return problem( // helper, формирующий RFC7807
                status: 404,
                title: 'PostType not found',
                detail: "Unknown post type slug: {$slug}"
            );
        }

        $res = new PostTypeResource($type);
        $response = $res->toResponse(request());
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Vary', 'Cookie', false);

        return $response;
    }

    public function update(UpdatePostTypeRequest $request, string $slug): Response
    {
        $type = PostType::query()->where('slug', $slug)->first();

        if (!$type) {
            return problem(status: 404, title: 'PostType not found', detail: "Unknown post type slug: {$slug}");
        }

        DB::transaction(function () use ($type, $request) {
            $type->options_json = $request->validated('options_json');
            $type->save();
        });

        $type->refresh();

        $res = new PostTypeResource($type);
        $response = $res->toResponse(request());
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Vary', 'Cookie', false);

        return $response;
    }
}
```

### 11.3 FormRequest

```php
<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage.posttypes') ?? false;
    }

    public function rules(): array
    {
        return [
            'options_json' => ['required', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'options_json.required' => 'The options_json field is required.',
            'options_json.array' => 'The options_json field must be an object.',
        ];
    }
}
```

### 11.4 Resource

```php
<?php
namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PostTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label ?? $this->name ?? $this->slug,
            'options_json' => $this->options_json ?? new \stdClass(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
```

### 11.5 Модель (фрагмент)

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostType extends Model
{
    protected $table = 'post_types';

    protected $casts = [
        'options_json' => 'array',
    ];

    protected $fillable = ['options_json'];
}
```

## 12) Примеры `curl`

GET:
```bash
curl -i \
  -H 'Cookie: cms_at=token' \
  https://api.example.com/api/v1/admin/post-types/page
```

PUT:
```bash
curl -i -X PUT \
  -H 'Content-Type: application/json' \
  -H 'Cookie: cms_at=token' \
  -d '{"options_json":{"template":"landing","seo":{"noindex":false}}}' \
  https://api.example.com/api/v1/admin/post-types/page
```

Ожидается `200 OK`, тело — актуальный `options_json`. Ответы админки имеют `Cache-Control: private, no-store` и `Vary: Cookie`.
