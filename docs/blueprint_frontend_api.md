# Blueprint API — документация для фронтенда

## Общие сведения

**Blueprint** — система схем полей для Entry. Определяет структуру данных контента.

**Типы Blueprint:**
- `full` — полная схема для PostType (требует `post_type_id`)
- `component` — переиспользуемый набор полей, встраиваемый в другие Blueprint

**Базовый URL:** `/api/v1/admin`

**Аутентификация:** JWT-токен в заголовке `Authorization: Bearer {token}`

---

## Endpoints

### 1. Список Blueprint

**Метод:** `GET /blueprints`  
**Route name:** `admin.v1.blueprints.index`

#### Query-параметры

| Параметр | Тип | Описание |
|----------|-----|----------|
| `post_type_id` | integer | Фильтр по PostType |
| `type` | string | Фильтр по типу: `full`, `component` |
| `page` | integer | Номер страницы (пагинация по 20) |

#### Ответ 200

```json
{
  "data": [
    {
      "id": 1,
      "post_type_id": 1,
      "slug": "article-basic",
      "name": "Базовая статья",
      "description": "Схема для обычных статей",
      "type": "full",
      "is_default": true,
      "created_at": "2025-11-19T12:00:00.000000Z",
      "updated_at": "2025-11-19T12:00:00.000000Z",
      "post_type": {
        "id": 1,
        "slug": "article",
        "name": "Статьи"
      },
      "entries_count": 42
    }
  ],
  "links": {...},
  "meta": {...}
}
```

---

### 2. Показать Blueprint

**Метод:** `GET /blueprints/{blueprint}`  
**Route name:** `admin.v1.blueprints.show`

#### Параметры URL

| Параметр | Тип | Описание |
|----------|-----|----------|
| `blueprint` | integer | ID Blueprint |

#### Ответ 200

```json
{
  "data": {
    "id": 1,
    "post_type_id": 1,
    "slug": "article-basic",
    "name": "Базовая статья",
    "description": "Схема для обычных статей",
    "type": "full",
    "is_default": true,
    "created_at": "2025-11-19T12:00:00.000000Z",
    "updated_at": "2025-11-19T12:00:00.000000Z",
    "post_type": {
      "id": 1,
      "slug": "article",
      "name": "Статьи"
    },
    "paths": [
      {
        "id": 1,
        "blueprint_id": 1,
        "name": "title",
        "full_path": "title",
        "data_type": "string",
        "cardinality": "one",
        "is_indexed": true,
        "is_required": true,
        "is_materialized": false,
        "is_embedded_blueprint": false,
        "is_ref": false,
        "is_many": false
      }
    ]
  }
}
```

---

### 3. Создать Blueprint

**Метод:** `POST /blueprints`  
**Route name:** `admin.v1.blueprints.store`

#### Тело запроса

```json
{
  "slug": "article-extended",
  "name": "Расширенная статья",
  "description": "Статья с дополнительными полями",
  "type": "full",
  "is_default": false,
  "post_type_id": 1
}
```

#### Правила валидации

| Поле | Правила |
|------|---------|
| `slug` | required, string, max:255, regex:`^[a-z0-9_-]+$`, уникален в рамках type+post_type_id |
| `name` | required, string, max:255 |
| `description` | nullable, string |
| `type` | required, in:`full`,`component` |
| `is_default` | nullable, boolean |
| `post_type_id` | nullable, integer, exists:post_types,id; обязателен для `type=full`, должен быть null для `type=component` |

#### Ответ 200

Возвращает созданный Blueprint (структура как в GET /blueprints/{blueprint}).

---

### 4. Обновить Blueprint

**Метод:** `PUT /blueprints/{blueprint}`  
**Route name:** `admin.v1.blueprints.update`

#### Параметры URL

| Параметр | Тип | Описание |
|----------|-----|----------|
| `blueprint` | integer | ID Blueprint |

#### Тело запроса

Аналогично POST (все поля опциональны).

#### Ответ 200

Возвращает обновлённый Blueprint.

---

### 5. Удалить Blueprint

**Метод:** `DELETE /blueprints/{blueprint}`  
**Route name:** `admin.v1.blueprints.destroy`

#### Параметры URL

| Параметр | Тип | Описание |
|----------|-----|----------|
| `blueprint` | integer | ID Blueprint |

#### Ответ 200

```json
{
  "message": "Blueprint deleted"
}
```

#### Ответ 422 (конфликт)

Невозможно удалить Blueprint, к которому привязаны Entry.

```json
{
  "message": "Cannot delete Blueprint with existing entries",
  "entries_count": 15
}
```

---

## Управление Paths (полями Blueprint)

### 6. Список Paths

**Метод:** `GET /blueprints/{blueprint}/paths`  
**Route name:** `admin.v1.blueprints.paths.index`

#### Query-параметры

| Параметр | Тип | Описание |
|----------|-----|----------|
| `own_only` | boolean | Если `true`, вернёт только собственные Paths (без материализованных из компонентов) |

#### Ответ 200

```json
{
  "data": [
    {
      "id": 1,
      "blueprint_id": 1,
      "source_component_id": null,
      "source_path_id": null,
      "parent_id": null,
      "name": "title",
      "full_path": "title",
      "data_type": "string",
      "cardinality": "one",
      "is_indexed": true,
      "is_required": true,
      "ref_target_type": null,
      "embedded_blueprint_id": null,
      "embedded_root_path_id": null,
      "validation_rules": {"min": 5, "max": 200},
      "ui_options": {"placeholder": "Введите заголовок"},
      "created_at": "2025-11-19T12:00:00.000000Z",
      "updated_at": "2025-11-19T12:00:00.000000Z",
      "is_materialized": false,
      "is_embedded_blueprint": false,
      "is_ref": false,
      "is_many": false
    }
  ]
}
```

---

### 7. Показать Path

**Метод:** `GET /blueprints/{blueprint}/paths/{path}`  
**Route name:** `admin.v1.blueprints.paths.show`

#### Параметры URL

| Параметр | Тип | Описание |
|----------|-----|----------|
| `blueprint` | integer | ID Blueprint |
| `path` | integer | ID Path |

#### Ответ 200

Возвращает один Path (структура как в списке).

---

### 8. Создать Path

**Метод:** `POST /blueprints/{blueprint}/paths`  
**Route name:** `admin.v1.blueprints.paths.store`

#### Тело запроса

```json
{
  "name": "excerpt",
  "full_path": "excerpt",
  "data_type": "text",
  "cardinality": "one",
  "is_indexed": true,
  "is_required": false,
  "validation_rules": {"max": 500},
  "ui_options": {"rows": 3}
}
```

#### Правила валидации

| Поле | Правила |
|------|---------|
| `blueprint_id` | sometimes, required, integer, exists:blueprints,id (автоматически устанавливается из URL) |
| `name` | required, string, max:100, regex:`^[a-zA-Z_][a-zA-Z0-9_]*$` |
| `full_path` | required, string, max:500, уникален в рамках Blueprint |
| `data_type` | required, in:`string`,`int`,`float`,`bool`,`text`,`json`,`ref`,`blueprint` |
| `cardinality` | required, in:`one`,`many` |
| `is_indexed` | nullable, boolean (для `data_type=blueprint` должен быть `false`) |
| `is_required` | nullable, boolean |
| `ref_target_type` | nullable, string, max:100, обязателен для `data_type=ref` |
| `embedded_blueprint_id` | nullable, integer, exists:blueprints,id, required_if:data_type,blueprint; должен указывать на `type=component`; нельзя встроить Blueprint сам в себя |
| `validation_rules` | nullable, array |
| `ui_options` | nullable, array |
| `parent_id` | nullable, integer, exists:paths,id (запрещено в component Blueprint) |

#### Ответ 200

Возвращает созданный Path.

**Примечание:** Если создаётся Path с `data_type=blueprint`, автоматически запускается материализация полей из встроенного компонента (через `PathObserver`).

---

### 9. Обновить Path

**Метод:** `PUT /blueprints/{blueprint}/paths/{path}`  
**Route name:** `admin.v1.blueprints.paths.update`

#### Параметры URL

| Параметр | Тип | Описание |
|----------|-----|----------|
| `blueprint` | integer | ID Blueprint |
| `path` | integer | ID Path |

#### Тело запроса

Аналогично POST (все поля опциональны).

#### Ответ 200

Возвращает обновлённый Path.

**Примечание:** При изменении `embedded_blueprint_id` автоматически перематериализуются вложенные Paths.

---

### 10. Удалить Path

**Метод:** `DELETE /blueprints/{blueprint}/paths/{path}`  
**Route name:** `admin.v1.blueprints.paths.destroy`

#### Параметры URL

| Параметр | Тип | Описание |
|----------|-----|----------|
| `blueprint` | integer | ID Blueprint |
| `path` | integer | ID Path |

#### Ответ 200

```json
{
  "message": "Path deleted"
}
```

**Примечание:** При удалении Path с `data_type=blueprint` автоматически удаляются все материализованные вложенные Paths (через `PathObserver`).

---

## Типы данных Path (data_type)

| Тип | Описание |
|-----|----------|
| `string` | Короткая строка |
| `int` | Целое число |
| `float` | Число с плавающей точкой |
| `bool` | Логическое значение |
| `text` | Длинный текст |
| `json` | JSON-данные |
| `ref` | Ссылка на другой Entry (требует `ref_target_type`) |
| `blueprint` | Встроенный компонент (требует `embedded_blueprint_id`, указывающего на `type=component`) |

---

## Cardinality

| Значение | Описание |
|----------|----------|
| `one` | Одиночное значение |
| `many` | Массив значений |

---

## Флаги в PathResource

| Флаг | Описание |
|------|----------|
| `is_materialized` | `true`, если Path материализован из компонента (`source_component_id != null`) |
| `is_embedded_blueprint` | `true`, если `data_type=blueprint` |
| `is_ref` | `true`, если `data_type=ref` |
| `is_many` | `true`, если `cardinality=many` |

---

## Встраивание компонентов

1. Создайте Blueprint с `type=component` (без `post_type_id`)
2. Добавьте в него Paths
3. В другом Blueprint создайте Path с:
   - `data_type=blueprint`
   - `embedded_blueprint_id={id компонента}`
4. Система автоматически материализует Paths из компонента в целевой Blueprint с префиксом `{full_path поля}.{full_path из компонента}`

**Пример:**

- Component `author` имеет Paths: `name`, `email`
- Full Blueprint `article` имеет Path `author` с `data_type=blueprint`, `embedded_blueprint_id=1`
- Результат: материализуются Paths `author.name`, `author.email` в Blueprint `article`

**Ограничения:**

- Компоненты не могут иметь вложенные Paths (`parent_id` запрещён)
- Компоненты не могут быть встроены сами в себя (защита от циклических ссылок)
- Поля с `data_type=blueprint` не могут быть индексируемыми

---

## Кеширование

Blueprint кеширует список всех Paths (собственных + материализованных) на 1 час. Кеш автоматически инвалидируется при:

- Создании/обновлении/удалении Path
- Материализации компонента

**Ключ кеша:** `blueprint:{id}:all_paths`

---

## Коды ответов

| Код | Описание |
|-----|----------|
| 200 | Успех |
| 201 | Создано (не используется, возвращается 200) |
| 422 | Ошибка валидации или бизнес-логики |
| 404 | Ресурс не найден |
| 401 | Не авторизован |
| 403 | Доступ запрещён |

---

## Примеры использования

### Создание Full Blueprint

```javascript
POST /api/v1/admin/blueprints
{
  "slug": "product",
  "name": "Товар",
  "type": "full",
  "post_type_id": 2,
  "is_default": true
}
```

### Создание Component Blueprint

```javascript
POST /api/v1/admin/blueprints
{
  "slug": "seo-meta",
  "name": "SEO метаданные",
  "type": "component",
  "description": "Переиспользуемый набор SEO-полей"
}
// Добавить Paths в компонент
POST /api/v1/admin/blueprints/5/paths
{
  "name": "title",
  "full_path": "title",
  "data_type": "string",
  "cardinality": "one",
  "is_required": true
}
```

### Встраивание компонента

```javascript
POST /api/v1/admin/blueprints/1/paths
{
  "name": "seo",
  "full_path": "seo",
  "data_type": "blueprint",
  "cardinality": "one",
  "embedded_blueprint_id": 5,
  "is_indexed": false
}
// После этого автоматически создастся материализованный Path:
// {
//   "full_path": "seo.title",
//   "source_component_id": 5,
//   "embedded_root_path_id": {id поля seo},
//   ...
// }
```

### Получение только собственных Paths

```javascript
GET /api/v1/admin/blueprints/1/paths?own_only=true
// Вернёт только Paths без source_component_id
```

---

## Связанные сущности

- **PostType** — тип контента (статьи, товары и т.п.)
- **Entry** — запись контента, использующая Blueprint как схему
- **DocValue** — индексированные скалярные значения полей Entry
- **DocRef** — индексированные ссылки между Entry

---

*Дата создания: 2025-11-19*

