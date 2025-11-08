# Navigation Hierarchy

1. `/docs/00-start/quick-context.md` — вход.
2. Доменные идеи: `/docs/10-concepts/*.md`, глоссарий.
3. API: `/docs/30-reference/api.md`; маршруты `_generated/routes.md`; ошибки RFC7807.
4. Данные: `_generated/erd.svg` → миграции → модели.
5. Спецтемы: slugs/media/search/security.
6. Архитектура: C4, ADR, invariants.

Если артефакт отсутствует → `docs:gap(<path>)` и укажи генератор из `composer docs:gen`.
