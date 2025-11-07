# Задача 43. REST каркас `/api/v1`

## Резюме
Собрать каркас REST‑API:
- Версионирование путей через префикс `/api/v1`.
- Базовый `ApiController` с хелперами ответа.
- Единый формат ошибок **RFC7807** (`application/problem+json`) для 404/422/401/403 и пр.

**Критерии приёмки:** 404/422 возвращают JSON с `type/title/status/detail` (и `errors` для 422).

Связанные: 37–41 (auth), 32–35 (views), 45 (кеш), 47 (документация OpenAPI, позже).

---

## Маршрутизация и структура
`routes/api.php`:
```php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;

Route::prefix('/v1')->group(function(){
    Route::get('/health', [HealthController::class, 'show']);
    // ... прочие v1 эндпоинты
});
```

Директория контроллеров: `app/Http/Controllers/Api/*`.

---

## Базовый контроллер и ответы
`app/Http/Controllers/Api/ApiController.php`:
```php
namespace App\Http\Controllers\Api;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\JsonResponse;

abstract class ApiController extends BaseController
{
    protected function ok(array $data = [], int $code = 200): JsonResponse
    { return response()->json($data, $code); }

    protected function problem(string $type, string $title, int $status, string $detail = '', array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], $extra), $status)->withHeaders(['Content-Type' => 'application/problem+json']);
    }
}
```

Пример контроллера `HealthController`:
```php
final class HealthController extends ApiController
{
    public function show(): \Illuminate\Http\JsonResponse
    { return $this->ok(['status' => 'ok']); }
}
```

---

## Глобальный формат ошибок (Handler)
`app/Exceptions/Handler.php`:
```php
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

public function register(): void
{
    $this->renderable(function (ValidationException $e, $request) {
        if ($request->is('api/*')) {
            $payload = [
                'type' => 'https://stupidcms.dev/problems/validation-error',
                'title' => 'Validation Failed',
                'status' => 422,
                'detail' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ];
            return response()->json($payload, 422)->withHeaders(['Content-Type' => 'application/problem+json']);
        }
    });

    $this->renderable(function (NotFoundHttpException $e, $request) {
        if ($request->is('api/*')) {
            $payload = [
                'type' => 'https://stupidcms.dev/problems/not-found',
                'title' => 'Not Found',
                'status' => 404,
                'detail' => 'The requested resource was not found.',
            ];
            return response()->json($payload, 404)->withHeaders(['Content-Type' => 'application/problem+json']);
        }
    });

    // Аналогично можно добавить 401/403/409 и т.д.
}
```

Опционально — middleware, требующее `Accept: application/json` для `api/*` и подставляющее `Content-Type` в проблемных ответах.

---

## Стандартизация пагинации/фильтров
Добавить соглашения:
- Параметры `page`, `per_page` (макс 100).
- Заголовки `X-Total-Count` и `Link: <...>; rel="next"`.
- Сортировка `sort=-created_at,title`.
(Реализация — в задачах ресурсов.)

---

## Тесты (Feature)
```php
public function test_health_ok()
{
    $this->getJson('/api/v1/health')->assertOk()->assertJson(['status' => 'ok']);
}

public function test_404_problem_json()
{
    $this->getJson('/api/v1/__nope__')->assertStatus(404)
        ->assertJsonStructure(['type','title','status','detail']);
}

public function test_422_problem_json()
{
    Route::post('/api/v1/_validate', function(\Illuminate\Http\Request $r){
        $r->validate(['email' => 'required|email']);
        return response()->json(['ok' => true]);
    });
    $this->postJson('/api/v1/_validate', [])->assertStatus(422)
         ->assertJsonStructure(['type','title','status','detail','errors']);
}
```

---

## Приёмка (Definition of Done)
- [ ] Все `api/*` работают под префиксом `/api/v1`.
- [ ] Базовый `ApiController` и `problem()` доступны.
- [ ] 404/422 возвращают RFC7807 JSON.

---

## Дополнительно
- Рассмотреть middleware, принудительно выставляющее `Cache-Control: no-store` для всего `api/*` (если нет ETag‑стратегии).
- Включить `Vary: Origin`/`Cookie` (см. Задачу 42).

