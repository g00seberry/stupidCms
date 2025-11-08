# Coding Standards (Laravel 12)

**PHP**

-   `declare(strict_types=1);`
-   Явные return types; readonly, enums, attributes.
-   Null-safe op, early return, маленькие функции.

**Laravel**

-   FormRequest для валидации; API Resources для ответа.
-   Контроллеры тонкие; бизнес — в Actions/Services (app/Domain/\*).
-   Policies/Gate — обязательны для админских операций.

**Testing (Pest)**

-   Feature: HTTP контракты/интеграции.
-   Unit: доменная логика.
-   Имена: `it('saves slug history on update')`.

**DB**

-   Миграции обратимы; индексы; timestamps; softDeletes где уместно.

**API**

-   REST, корректные коды; RFC7807 для ошибок; пагинация стандартная; версионирование `/api/v1`.
