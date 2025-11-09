---
owner: "@architect-team"
system_of_record: "narrative"
review_cycle_days: 365
last_reviewed: 2025-11-09
related_code:
    - "routes/web_admin.php"
    - "app/Http/Controllers/Admin/*"
    - "resources/views/admin/*"
---

# ADR-0005: Use Blade for Admin Panel

**Status**: Accepted

**Date**: 2025-11-09

**Deciders**: Backend team, Frontend team

**Related**: [ADR-0001: JWT Authentication](0001-jwt-authentication.md)

---

## Context

stupidCms требует административной панели для управления контентом.

**Требования**:

-   Простая разработка и поддержка силами backend-команды
-   Быстрый time-to-market
-   Нет необходимости в complex state management
-   Админка используется только внутренними пользователями (не публичный интерфейс)
-   Безопасность и простота аутентификации

---

## Decision

Используем **Laravel Blade** для административной панели.

**Обоснование**:

-   **Простота**: стандартный Laravel-стек, без дополнительной сборки frontend
-   **Скорость разработки**: backend-команда может работать без знания Vue/React
-   **Безопасность**: session-based auth проще JWT для same-domain приложения
-   **Performance**: серверный рендеринг быстрее для первой загрузки
-   **Меньше зависимостей**: не нужен Node.js, npm, webpack/vite для админки
-   **Maintainability**: меньше движущихся частей, проще CI/CD

---

## Implementation

### Структура

```
routes/
  └── web_admin.php          # Админские роуты (GET /admin/*)

app/Http/Controllers/Admin/  # Контроллеры админки (Blade)
  ├── DashboardController.php
  ├── EntriesController.php
  └── ...

resources/views/admin/        # Blade шаблоны
  ├── layouts/
  │   └── app.blade.php
  ├── dashboard.blade.php
  └── entries/
      ├── index.blade.php
      ├── create.blade.php
      └── edit.blade.php
```

### Аутентификация

| Компонент     | Тип           | Middleware        | Route Group       |
| ------------- | ------------- | ----------------- | ----------------- |
| **Админка**   | Session-based | `web`, `auth`     | `/admin/*`        |
| **Admin API** | JWT           | `api`, `auth:jwt` | `/api/v1/admin/*` |

### Интерактивность (по мере необходимости)

-   **Alpine.js** — для простых взаимодействий (dropdowns, modals)
-   **Livewire** — для rich UI без написания JavaScript
-   **Local Vue components** — для сложных виджетов (media picker, drag-and-drop)

---

## References

-   [Laravel Blade Documentation](https://laravel.com/docs/blade)
-   [Alpine.js](https://alpinejs.dev/)
-   [Laravel Livewire](https://livewire.laravel.com/)
-   [Inertia.js](https://inertiajs.com/)

---

## History

| Date       | Change   | Author        |
| ---------- | -------- | ------------- |
| 2025-11-09 | Created  | @backend-team |
| 2025-11-09 | Accepted | @team         |

---

> 💡 **Future Consideration**: Если административная панель потребует сложного UI (real-time collaboration, complex state management), можно мигрировать на SPA. Admin API уже готов для этого.
