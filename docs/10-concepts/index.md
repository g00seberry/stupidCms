---
owner: "@backend-team"
system_of_record: "narrative"
review_cycle_days: 90
last_reviewed: 2025-11-08
---

# Концепции stupidCms

Здесь описаны ключевые концепции и бизнес-логика stupidCms.

## Навигация

-   **[Модель данных](domain-model.md)** — обзор сущностей и связей
-   **[Post Types](post-types.md)** — типы контента
-   **[Entry](entries.md)** — записи контента
-   **[Slugs & 301](slugs.md)** — URL и редиректы
-   **[Taxonomy](taxonomy.md)** — таксономия и термины
-   **[Media](media.md)** — медиатека
-   **[Search](search.md)** — полнотекстовый поиск
-   **[Options](options.md)** — настройки сайта

## Философия

stupidCms построен на нескольких ключевых принципах:

### 1. **Гибкость через Post Types**

Вместо жёстко заданных типов контента (блог, новости, страницы), stupidCms позволяет создавать любые типы через API или админку. Каждый Post Type — это шаблон с настройками:

-   Какие поля доступны
-   Какие таксономии применимы
-   Поддержка медиа
-   Правила публикации

### 2. **URL как first-class citizen**

Slugs — не просто строка в таблице entries, а отдельная сущность с историей. При изменении URL:

-   Старый slug сохраняется
-   Автоматически создаётся 301-редирект
-   Поддержка иерархии (parent/child)
-   Защита от конфликтов с системными маршрутами

### 3. **Immutable Audit Trail**

Каждое изменение записывается в `audits`:

-   Кто изменил
-   Что изменил (diff)
-   Когда изменил
-   IP адрес, user agent

Это даёт полную прозрачность и возможность rollback.

### 4. **API-first**

Все операции доступны через REST API. Admin UI — это просто один из клиентов. Вы можете:

-   Интегрироваться с мобильными приложениями
-   Создать кастомную админку
-   Автоматизировать публикации через CI/CD

### 5. **Type Safety**

-   Strict types в PHP
-   Form Requests для валидации
-   API Resources для сериализации
-   PHPStan level max

## Основные сущности

| Сущность         | Описание                                  | Концепция                   |
| ---------------- | ----------------------------------------- | --------------------------- |
| **PostType**     | Тип контента (статья, товар, событие)     | [Post Types](post-types.md) |
| **Entry**        | Запись (экземпляр PostType)               | [Entries](entries.md)       |
| **EntrySlug**    | История URL с 301                         | [Slugs](slugs.md)           |
| **Taxonomy**     | Группа терминов                           | [Taxonomy](taxonomy.md)     |
| **Term**         | Категория, тег                            | [Taxonomy](taxonomy.md)     |
| **Media**        | Медиафайл                                 | [Media](media.md)           |
| **MediaVariant** | Варианты изображения (thumb, medium, etc) | [Media](media.md)           |
| **Option**       | Настройка сайта (key-value)               | [Options](options.md)       |
| **User**         | Пользователь с правами                    | -                           |
| **Redirect**     | Ручной 301-редирект                       | [Slugs](slugs.md)           |

## Жизненный цикл Entry

1. **Draft** — создаётся как черновик (`published_at = null`)
2. **Scheduled** — задаётся `published_at` в будущем
3. **Published** — `published_at` в прошлом, `unpublished_at = null`
4. **Unpublished** — `unpublished_at` в прошлом

Entry может вернуться из published в draft или быть заархивирована.

Подробнее: [Entries — Publishing Flow](entries.md#publishing-flow)

## Иерархия и связи

### Parent-Child (Entry → Entry)

Entries могут иметь родителя (например, "О компании" → "Наша команда").

```
Entry: "О компании" (slug: /about)
  └─ Entry: "Наша команда" (slug: /about/team)
  └─ Entry: "Контакты" (slug: /about/contacts)
```

### Taxonomies → Terms → Entries

```
Taxonomy: "Категории статей"
  ├─ Term: "Технологии"
  │   ├─ Entry: "Laravel 12 вышел"
  │   └─ Entry: "PHP 8.3 новинки"
  └─ Term: "Дизайн"
      └─ Entry: "10 трендов 2025"
```

Подробнее: [Taxonomy](taxonomy.md)

### Media → Entries

Связь many-to-many через `entry_media`:

```
Entry: "Laravel 12 вышел"
  ├─ Media: "laravel-logo.png" (featured_image)
  └─ Media: "screenshot.png" (gallery)
```

Подробнее: [Media](media.md)

## Reserved Routes

stupidCms защищает системные URL от конфликтов с пользовательскими slugs:

-   `/api/*` — API
-   `/admin/*` — Admin UI
-   `/auth/*` — Аутентификация
-   Кастомные из `reserved_routes` таблицы

При попытке создать Entry со слагом `/api/test` будет ошибка валидации.

## Аутентификация и авторизация

-   **JWT токены** (HS256 или RS256) для API
-   **Refresh tokens** для продления сессии
-   **HTTP-only cookies** для безопасности
-   **Policies** для проверки прав (`can('update', $entry)`)

Подробнее: [Security](../40-architecture/security.md)

## Events & Listeners

stupidCms использует события для расширяемости:

-   `EntrySlugChanged` — при изменении slug
-   `OptionChanged` — при изменении опции
-   _(добавить другие по мере реализации)_

Подробнее: [Events Reference](../30-reference/events.md)

## Следующие шаги

Рекомендуем изучить концепции в порядке:

1. [Модель данных](domain-model.md) — общая схема
2. [Post Types](post-types.md) → [Entries](entries.md) — основа контента
3. [Slugs](slugs.md) — маршрутизация
4. [Taxonomy](taxonomy.md) — категоризация
5. [Media](media.md) — работа с файлами
6. [Search](search.md) — поиск по контенту

---

**Есть вопросы?** Загляните в [Глоссарий](../70-glossary/index.md) или [How-to Guides](../20-how-to/index.md).
