# Answer Examples (short)

### A) «Как получить посты по таксономии?»

1. Открой Scribe: `docs/_generated/api-docs/index.html`.
2. Найди endpoint `/api/v1/entries?term=...`.
3. Ответи: метод, путь, query, 200/404, пример JSON. Пометь `requires: docs:gen`.

### B) «Как работает история слугов?»

1. `/docs/10-concepts/slugs.md` → затем `app/Support/Slug/SlugResolver.php`.
2. Опиши: создание записи в `entry_slugs`, 301 со старых.
3. Дай пример `GET /{old-slug}` → 301 Location: `/new-slug`.

### C) «Добавить PostType `news`»

1. `/docs/20-how-to/add-post-type.md`
2. Шаги: миграция/сид/модель/валидатор/ресурс; проверка маршрутов; тест на CRUD.
3. Acceptance: записи создаются, slug уникален, в админке виден.

### D) «Какие права нужны админ-API Entries?»

1. `/docs/_generated/permissions.md`, `app/Policies/EntryPolicy.php`.
2. Таблица: ability → метод → заметки.
3. Если нет в permissions.md → `requires: docs:gen`.

### E) «Где смотреть конфиг медиа?»

-   `config/filesystems.php`, `/docs/30-reference/media-pipeline.md`, модели `Media*`.
-   Дай схему потока: upload → normalize → variants → signed URL.
