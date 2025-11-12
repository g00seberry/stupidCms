# Workflow

1. Understand: открой нужные /docs + посмотри код.
2. Plan: найди существующий паттерн (Actions/Events/Jobs/Policies).
3. Implement: строго типизированный код + тесты.
4. Document: обнови /docs при изменении поведения.
5. Verify: `composer docs:gen`, `php artisan test`, PHPStan.

**Обязательные шаги в конце каждой задачи:**

После завершения реализации **ОБЯЗАТЕЛЬНО** выполнить:

1. **Запуск всех тестов:**

    ```bash
    php artisan test
    ```

    Все тесты должны проходить. Если есть падающие тесты — исправить перед завершением задачи.

2. **Генерация документации:**
    ```bash
    composer docs:gen
    ```
    Обновляет автогенерируемые артефакты: ERD, Routes, Permissions, API docs и т.д.

**Правило:** Никогда не завершать задачу без успешного прохождения тестов и генерации документации.

**Acceptance checklist (общий)**

-   [ ] Все тесты проходят (`php artisan test`)
-   [ ] Документация сгенерирована (`composer docs:gen`)
-   [ ] Сгенерированы ERD/Routes/Permissions (если релевантно)
-   [ ] Есть тесты (feature/unit)
-   [ ] Обновлён frontmatter (owner, last_reviewed)
-   [ ] Никаких «висячих» TODO без issue/ADR
