# Workflow

1. Understand: открой нужные /docs + посмотри код.
2. Plan: найди существующий паттерн (Actions/Events/Jobs/Policies).
3. Implement: строго типизированный код + тесты.
4. Document: обнови /docs при изменении поведения.
5. Verify: `composer docs:gen`, `php artisan test`, PHPStan.

**Acceptance checklist (общий)**

-   [ ] Сгенерированы ERD/Routes/Permissions (если релевантно)
-   [ ] Есть тесты (feature/unit)
-   [ ] Обновлён frontmatter (owner, last_reviewed)
-   [ ] Никаких «висячих» TODO без issue/ADR
