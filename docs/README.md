# stupidCms Documentation

Полная документация stupidCms в формате docs-as-code.

## 🚀 Быстрый старт

### Генерация документации из кода

```bash
# Сгенерировать всю автоматическую документацию
composer docs:gen

# Или отдельные части
php artisan docs:routes      # Маршруты
php artisan docs:abilities   # Права доступа
php artisan docs:erd          # ERD схема БД
php artisan docs:errors       # Коды ошибок
php artisan docs:config       # Конфигурация
php artisan docs:search       # Elasticsearch маппинги
php artisan docs:media        # Media pipeline
```

## 📁 Структура

```
docs/
├── 00-start/           # Начало работы
│   ├── index.md        # Главная страница
│   ├── quick-context.md # Быстрый контекст (для AI)
│   └── installation.md  # Установка
│
├── 10-concepts/        # Концепции и объяснения
│   ├── domain-model.md
│   ├── post-types.md
│   ├── entries.md
│   ├── slugs.md
│   ├── taxonomy.md
│   ├── media.md
│   ├── search.md
│   └── options.md
│
├── 20-how-to/          # Пошаговые инструкции
│   └── ...
│
├── 30-reference/       # Справочная информация
│   ├── erd.md          # Схема БД
│   ├── routes.md       # Маршруты
│   ├── permissions.md  # Права доступа
│   ├── config.md       # Конфигурация
│   ├── events.md       # События
│   ├── errors.md       # Коды ошибок (RFC7807)
│   ├── media-pipeline.md
│   └── search-mappings.md
│
├── 40-architecture/    # Архитектурные решения
│   ├── c4.md           # C4 диаграммы
│   ├── adr/            # Architecture Decision Records
│   ├── invariants.md
│   ├── perf-cache.md
│   └── security.md
│
├── 50-operations/      # DevOps и операции
│   ├── ci-cd.md
│   ├── backups.md
│   ├── monitoring.md
│   └── feature-flags.md
│
├── 60-admin/           # Admin UI
│   ├── scenarios.md
│   └── roles.md
│
├── 70-glossary/        # Глоссарий
│   └── index.md
│
├── _assets/            # Статические файлы (изображения, CSS, JS)
├── _generated/         # АВТОГЕНЕРАЦИЯ (не редактировать вручную!)
│   ├── routes.json
│   ├── routes.md
│   ├── permissions.json
│   ├── permissions.md
│   ├── erd.json
│   ├── erd.puml
│   ├── erd.mmd
│   ├── erd.svg
│   ├── errors.json
│   ├── errors.md
│   ├── config.json
│   ├── config.md
│   ├── search-mappings.json
│   ├── search-mappings.md
│   ├── media-pipeline.json
│   └── media-pipeline.md
│
└── _cursor/            # Промпты для AI (Cursor)
    └── prompts/
```

## 🤖 AI-Friendly (Cursor)

Документация оптимизирована для AI-ассистентов:

-   **`docs/_assistant/rules`** — правила для Cursor
-   **`docs/00-start/quick-context.md`** — 2-минутный обзор для AI
-   **`docs/70-glossary/index.md`** — термины проекта
-   **Frontmatter** в каждом файле — метаданные (owner, review_cycle, related_code)

## ✍️ Contribution

### Редактирование документации

1. Найдите нужный `.md` файл в `docs/`
2. Отредактируйте его
3. Обновите `last_reviewed` в frontmatter
4. Если меняли код — запустите `composer docs:gen`
5. Создайте PR

### Создание нового раздела

1. Создайте файл в нужной директории (например, `docs/20-how-to/new-guide.md`)
2. Добавьте frontmatter:
    ```yaml
    ---
    owner: "@team-name"
    system_of_record: "narrative"
    review_cycle_days: 60
    last_reviewed: 2025-11-08
    related_code:
        - "path/to/file.php"
    ---
    ```

### Создание ADR

1. Скопируйте `docs/40-architecture/adr/adr-template.md`
2. Переименуйте в `XXXX-title.md` (например, `0005-postgres-over-mysql.md`)
3. Заполните секции
4. Добавьте ссылку в `docs/40-architecture/adr/index.md`

### PHP (генераторы)

Уже включены в Laravel проект. См. `app/Console/Commands/Generate*Doc.php`.

## 🔗 Ссылки

-   [Diátaxis Framework](https://diataxis.fr/)
-   [Architecture Decision Records](https://adr.github.io/)
-   [RFC7807 Problem Details](https://tools.ietf.org/html/rfc7807)

---

**Вопросы?** Создайте issue или обсудите в команде.
