# Задача 42. Вариации CORS/headers

## Резюме
Настроить корректную работу с другим фронтенд‑origin (SPA/админка) и кэшированием:
- CORS для путей `api/*` с поддержкой **credentials** (cookies) и нужных заголовков (включая `X-CSRF-Token`).
- Preflight (OPTIONS) проходит успешно.
- На публичном фронте добавить `Vary: Cookie`, чтобы элементы типа «toolbar» не кэшировались общими прокси.

**Критерии приёмки:** preflight успешен (статус 204/200, заголовки allow*), фронтовой toolbar не кэшируется из‑за `Vary: Cookie`.

Связанные: 37–41 (auth/CSRF/admin), 45 (ResponseCache/CDN).

---

## CORS‑конфигурация
Laravel использует конфиг `config/cors.php` (HandleCors). Пример кастомизации:
```php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'https://admin.example.com,https://app.example.com')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type','Accept','X-CSRF-Token','X-Requested-With','Origin','Authorization'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => true, // cookies для JWT
];
```

В `App\Http\Kernel` глобальное middleware уже содержит `\Illuminate\Http\Middleware\HandleCors::class`.

### Проверка preflight
Запрос:
```
OPTIONS /api/v1/auth/login
Origin: https://admin.example.com
Access-Control-Request-Method: POST
Access-Control-Request-Headers: Content-Type, X-CSRF-Token
```
Ожидаемый ответ: `204 No Content` c заголовками `Access-Control-Allow-Origin: https://admin.example.com`, `Access-Control-Allow-Credentials: true`, `Access-Control-Allow-Headers: Content-Type, X-CSRF-Token`, `Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers`.

---

## Vary‑заголовки для фронта
Для страниц, где контент зависит от наличия cookies (например, отображение админ‑toolbar для залогиненных), добавляем заголовок `Vary: Cookie`.

### Middleware `AddVaryHeaders`
`app/Http/Middleware/AddVaryHeaders.php`:
```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class AddVaryHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        // Для web‑страниц, чтобы разделять кэш для гостей/юзеров
        if ($request->is('/') || $request->is('{slug}') || $request->is('*')) {
            $response->headers->set('Vary', trim($response->headers->get('Vary').' ,Cookie', ' ,'));
        }
        // Для CORS preflight/ответов добавит Vary по Origin/ACR*
        if ($request->is('api/*')) {
            $append = 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers';
            $response->headers->set('Vary', trim($response->headers->get('Vary').' ,'.$append, ' ,'));
        }
        return $response;
    }
}
```

Регистрация: добавить в группу `web` и/или глобально после кеширующих middleware.

---

## Тесты (Feature)
```php
public function test_preflight_is_ok()
{
    $this->withHeaders([
        'Origin' => 'https://admin.example.com',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'Content-Type, X-CSRF-Token',
    ])->options('/api/v1/auth/login')->assertStatus(204)
      ->assertHeader('Access-Control-Allow-Origin', 'https://admin.example.com')
      ->assertHeader('Access-Control-Allow-Credentials', 'true');
}

public function test_frontend_has_vary_cookie()
{
    $this->get('/')->assertHeaderContains('Vary', 'Cookie');
}
```

---

## Приёмка (Definition of Done)
- [ ] `config/cors.php` настроен: origin/headers/credentials/max_age.
- [ ] Preflight проходит с корректными заголовками.
- [ ] Публичные страницы выставляют `Vary: Cookie`.

---

## Примечания
- При использовании CDN/Reverse‑proxy: обязательно `Vary: Origin` + `Vary: Cookie` + корректная конфигурация кеширующих слоёв.
- `supports_credentials=true` требует **точное** совпадение origin (не `*`).

