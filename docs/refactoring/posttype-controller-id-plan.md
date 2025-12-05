# План: Перевод PostTypeController на использование ID

**Цель:** Перевести управление PostType с использования slug в URL на использование ID, чтобы соответствовать требованию "все взаимодействие с PostType через ID, кроме определения шаблона".

---

## Задачи (10 пунктов)

### 1. Обновить роуты в `routes/api_admin.php`
- **Файл:** `routes/api_admin.php`
- **Действия:**
  - Заменить `/post-types/{slug}` на `/post-types/{id}` в методах show, update, destroy
  - Добавить валидацию `->where('id', '[0-9]+')` для всех маршрутов с ID
  - Обновить имена роутов (если необходимо)
- **Изменения:**
  - `Route::get('/post-types/{slug}', ...)` → `Route::get('/post-types/{id}', ...)->where('id', '[0-9]+')`
  - `Route::put('/post-types/{slug}', ...)` → `Route::put('/post-types/{id}', ...)->where('id', '[0-9]+')`
  - `Route::delete('/post-types/{slug}', ...)` → `Route::delete('/post-types/{id}', ...)->where('id', '[0-9]+')`

### 2. Обновить методы PostTypeController: `show()`, `update()`, `destroy()`
- **Файл:** `app/Http/Controllers/Admin/V1/PostTypeController.php`
- **Действия:**
  - Заменить параметр `string $slug` на `int $id` во всех методах
  - Изменить запросы с `PostType::query()->where('slug', $slug)->first()` на `PostType::findOrFail($id)`
  - Обновить метод `throwPostTypeNotFound()` - принимать ID вместо slug
  - Убрать метод `throwPostTypeNotFound()` или оставить для обратной совместимости (но принимать ID)
- **Изменения:**
  - `public function show(string $slug)` → `public function show(int $id)`
  - `public function update(..., string $slug)` → `public function update(..., int $id)`
  - `public function destroy(..., string $slug)` → `public function destroy(..., int $id)`

### 3. Обновить UpdatePostTypeRequest для работы с ID
- **Файл:** `app/Http/Requests/Admin/UpdatePostTypeRequest.php`
- **Действия:**
  - Заменить `$this->route('slug')` на `$this->route('id')`
  - Получить PostType по ID и использовать его slug для проверки уникальности
  - Обновить PHPDoc комментарии
- **Изменения:**
  - Получить ID из роута: `$id = $this->route('id')`
  - Найти PostType: `$postType = PostType::findOrFail($id)`
  - В проверке уникальности: `Rule::unique('post_types', 'slug')->ignore($postType->slug, 'slug')`

### 4. Обновить PHPDoc комментарии в PostTypeController
- **Файл:** `app/Http/Controllers/Admin/V1/PostTypeController.php`
- **Действия:**
  - Заменить все `@urlParam slug` на `@urlParam id`
  - Обновить примеры в PHPDoc (например, `article` → `1`)
  - Обновить примеры ответов ошибок (404 с slug → 404 с ID)
- **Изменения:**
  - `@urlParam slug string required Slug PostType. Example: article` → `@urlParam id int required ID PostType. Example: 1`
  - Обновить примеры ошибок: `"Unknown post type slug: article"` → `"PostType not found: 1"`

### 5. Обновить все тесты для PostTypeController
- **Файлы:** `tests/Feature/Api/PostTypes/PostTypesTest.php` и другие
- **Действия:**
  - Найти все тесты, использующие PostTypeController
  - Заменить URL с `/post-types/article` на `/post-types/{id}`
  - Обновить все проверки ошибок (404 вместо slug использовать ID)
  - Обновить assertJsonPath для проверки ошибок
- **Изменения:**
  - `$this->getJson('/api/v1/admin/post-types/article')` → `$this->getJson("/api/v1/admin/post-types/{$postType->id}")`
  - Обновить проверки ошибок

### 6. Обновить метод throwPostTypeNotFound() в PostTypeController
- **Файл:** `app/Http/Controllers/Admin/V1/PostTypeController.php`
- **Действия:**
  - Изменить сигнатуру: `throwPostTypeNotFound(string $slug)` → `throwPostTypeNotFound(int $id)`
  - Обновить сообщение об ошибке: вместо slug использовать ID
  - Обновить meta с 'slug' на 'id'
- **Изменения:**
  - `sprintf('Unknown post type slug: %s', $slug)` → `sprintf('PostType not found: %d', $id)`
  - `['slug' => $slug]` → `['id' => $id]`

### 7. Проверить и обновить PostTypeResource (если необходимо)
- **Файл:** `app/Http/Resources/Admin/PostTypeResource.php`
- **Действия:**
  - Проверить, что ресурс возвращает правильные данные
  - Убедиться, что slug все еще возвращается (это нормально, т.к. это часть данных PostType)
  - Проверить, нет ли зависимостей от slug в URL
- **Статус:** Вероятно, изменения не нужны, т.к. ресурс просто возвращает данные модели

### 8. Проверить другие места использования PostTypeController
- **Действия:**
  - Найти все места, где вызываются роуты PostTypeController
  - Проверить фронтенд код (если есть ссылки в документации)
  - Обновить примеры в документации API
- **Файлы для проверки:**
  - Документация (docs/)
  - README файлы
  - Примеры использования

### 9. Обновить документацию и примеры
- **Файлы:** 
  - `docs/refactoring/frontend-summary.md`
  - `docs/refactoring/frontend-migration-guide.md`
  - Другие документы, где упоминается PostTypeController
- **Действия:**
  - Добавить информацию о breaking change для PostTypeController
  - Обновить примеры API запросов
  - Указать новые URL с ID вместо slug

### 10. Запустить тесты и проверить работоспособность
- **Действия:**
  - Запустить `php artisan test` для проверки всех тестов
  - Убедиться, что все тесты проходят
  - Проверить, что роуты работают корректно
  - Обновить документацию API (composer scribe:gen)

---

## Последовательность выполнения

1. Сначала обновить роуты (пункт 1)
2. Затем обновить контроллер (пункты 2, 4, 6)
3. Обновить Request класс (пункт 3)
4. Обновить тесты (пункт 5)
5. Проверить ресурсы и другие места (пункты 7, 8)
6. Обновить документацию (пункт 9)
7. Финальная проверка (пункт 10)

---

## Breaking Changes

⚠️ **Важно:** Это breaking change для API:
- Старые URL: `/api/v1/admin/post-types/article`
- Новые URL: `/api/v1/admin/post-types/1`

Фронтенд необходимо обновить для использования ID вместо slug в URL для PostTypeController.

