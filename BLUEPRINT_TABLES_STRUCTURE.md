# Структура таблиц системы Blueprints

## Обзор

Система Blueprints состоит из следующих основных таблиц:
1. **blueprints** - шаблоны структуры данных
2. **paths** - поля внутри blueprint'ов
3. **blueprint_embeds** - связи встраивания между blueprint'ами
4. **path_ref_constraints** - ограничения для ref-полей
5. **path_media_constraints** - ограничения для media-полей
6. **post_types** - типы записей (связаны с blueprints)
7. **form_configs** - конфигурации форм (связаны с blueprints)

---

## 1. Таблица `blueprints`

**Назначение:** Хранит схемы контента (blueprint'ы), определяющие структуру Entry.

### Структура полей:

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint unsigned | Первичный ключ |
| `name` | string | Название blueprint |
| `code` | string | Уникальный код blueprint |
| `description` | text nullable | Описание |
| `created_at` | timestamp | Дата создания |
| `updated_at` | timestamp | Дата обновления |

### Индексы:

- **Уникальный индекс:** `code` (уникальный код)

### Внешние ключи:

- **Исходящие:** нет
- **Входящие:**
  - `post_types.blueprint_id` → `blueprints.id` (RESTRICT ON DELETE)
  - `form_configs.blueprint_id` → `blueprints.id` (CASCADE ON DELETE)
  - `paths.blueprint_id` → `blueprints.id` (CASCADE ON DELETE)
  - `blueprint_embeds.blueprint_id` → `blueprints.id` (CASCADE ON DELETE)
  - `blueprint_embeds.embedded_blueprint_id` → `blueprints.id` (RESTRICT ON DELETE)

### Особенности:

- При удалении blueprint проверяется, что он не используется в PostType и не встроен в другие blueprint'ы
- Код должен быть уникальным

---

## 2. Таблица `paths`

**Назначение:** Хранит пути (поля) внутри blueprint'ов, включая информацию о типах данных, кардинальности, индексации и валидации.

### Структура полей:

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint unsigned | Первичный ключ |
| `blueprint_id` | bigint unsigned | Владелец поля (FK → blueprints.id) |
| `blueprint_embed_id` | bigint unsigned nullable | К какому embed привязано (FK → blueprint_embeds.id, NULL = собственное поле) |
| `parent_id` | bigint unsigned nullable | Родительский path (FK → paths.id, NULL = корневой уровень) |
| `name` | string | Локальное имя поля |
| `full_path` | string(2048) | Материализованный путь (например, 'author.contacts.phone') |
| `data_type` | enum | Тип данных: 'string', 'text', 'int', 'float', 'bool', 'datetime', 'json', 'ref', 'media' |
| `cardinality` | enum | Кардинальность: 'one' или 'many' (по умолчанию 'one') |
| `is_indexed` | boolean | Индексируется ли поле (по умолчанию false) |
| `validation_rules` | json nullable | JSON-правила валидации |
| `created_at` | timestamp | Дата создания |
| `updated_at` | timestamp | Дата обновления |

### Индексы:

1. **`blueprint_id`** - для быстрого поиска путей по blueprint
2. **`idx_paths_blueprint_parent`** - составной индекс `(blueprint_id, parent_id)` для иерархических запросов
3. **`idx_paths_embed`** - индекс `blueprint_embed_id` для поиска копий по embed
4. **`uq_paths_full_path_per_blueprint`** - уникальный индекс `(blueprint_id, full_path(766))` для MySQL (префикс из-за лимита длины ключа)
5. **`idx_paths_materialization_lookup`** - составной индекс `(blueprint_id, blueprint_embed_id, full_path(100))` для оптимизации batch insert в MaterializationService
6. **`idx_paths_conflict_check`** - составной индекс `(blueprint_id, full_path(766))` для оптимизации проверки конфликтов

### Внешние ключи:

- **Исходящие:**
  - `blueprint_id` → `blueprints.id` (CASCADE ON DELETE)
  - `blueprint_embed_id` → `blueprint_embeds.id` (CASCADE ON DELETE)
  - `parent_id` → `paths.id` (CASCADE ON DELETE)
- **Входящие:**
  - `paths.parent_id` → `paths.id` (самореференс)
  - `path_ref_constraints.path_id` → `paths.id` (CASCADE ON DELETE)
  - `path_media_constraints.path_id` → `paths.id` (CASCADE ON DELETE)
  - `blueprint_embeds.host_path_id` → `paths.id` (CASCADE ON DELETE)

### Особенности:

- **Собственные поля:** `blueprint_embed_id = NULL`
- **Скопированные поля:** имеют `blueprint_embed_id`, исходный blueprint можно получить через `blueprintEmbed->embeddedBlueprint`
- **Защита:** скопированные поля нельзя редактировать/удалять напрямую
- **Материализация:** `full_path` вычисляется и сохраняется при создании/копировании
- **Иерархия:** поддерживается через `parent_id` (дерево путей)

---

## 3. Таблица `blueprint_embeds`

**Назначение:** Хранит информацию о встроенных blueprint'ах внутри других blueprint'ов. Позволяет создавать иерархические структуры контента.

### Структура полей:

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint unsigned | Первичный ключ |
| `blueprint_id` | bigint unsigned | Кто встраивает (host blueprint, FK → blueprints.id) |
| `embedded_blueprint_id` | bigint unsigned | Кого встраивают (embedded blueprint, FK → blueprints.id) |
| `host_path_id` | bigint unsigned nullable | Под каким полем (FK → paths.id, NULL = встраивание в корень) |
| `created_at` | timestamp | Дата создания |
| `updated_at` | timestamp | Дата обновления |

### Индексы:

1. **`uq_blueprint_embed`** - уникальный составной индекс `(blueprint_id, embedded_blueprint_id, host_path_id)` - предотвращает дублирование встраиваний
2. **`idx_embeds_embedded`** - индекс `embedded_blueprint_id` для поиска, где встроен blueprint
3. **`idx_embeds_blueprint`** - индекс `blueprint_id` для поиска встраиваний blueprint'а

### Внешние ключи:

- **Исходящие:**
  - `blueprint_id` → `blueprints.id` (CASCADE ON DELETE)
  - `embedded_blueprint_id` → `blueprints.id` (RESTRICT ON DELETE)
  - `host_path_id` → `paths.id` (CASCADE ON DELETE)
- **Входящие:**
  - `paths.blueprint_embed_id` → `blueprint_embeds.id` (CASCADE ON DELETE)

### Особенности:

- **Уникальность:** один blueprint может быть встроен в другой несколько раз, но только под разными `host_path_id`
- **Встраивание в корень:** `host_path_id = NULL` означает встраивание на корневом уровне
- **Встраивание под полем:** `host_path_id` должен указывать на поле типа `json` (группа)
- **Каскадное удаление:** при удалении embed автоматически удаляются все скопированные пути (`paths.blueprint_embed_id`)

---

## 4. Таблица `path_ref_constraints`

**Назначение:** Ограничения на допустимые PostType для ref-полей.

### Структура полей:

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint unsigned | Первичный ключ |
| `path_id` | bigint unsigned | ID пути (FK → paths.id) |
| `allowed_post_type_id` | bigint unsigned | ID допустимого типа записи (FK → post_types.id) |
| `created_at` | timestamp | Дата создания |
| `updated_at` | timestamp | Дата обновления |

### Индексы:

1. **`uq_path_ref_constraints_path_post_type`** - уникальный индекс `(path_id, allowed_post_type_id)` - предотвращает дубликаты
2. **`idx_path_ref_constraints_path_id`** - индекс `path_id` для быстрых запросов по path
3. **`idx_path_ref_constraints_post_type_id`** - индекс `allowed_post_type_id` для обратных запросов

### Внешние ключи:

- **Исходящие:**
  - `path_id` → `paths.id` (CASCADE ON DELETE)
  - `allowed_post_type_id` → `post_types.id` (RESTRICT ON DELETE)

### Особенности:

- Используется только для полей типа `ref`
- Определяет, какие PostType могут быть использованы в качестве значения для ref-поля
- При удалении path автоматически удаляются все связанные constraints

---

## 5. Таблица `path_media_constraints`

**Назначение:** Ограничения на допустимые MIME-типы для media-полей.

### Структура полей:

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint unsigned | Первичный ключ |
| `path_id` | bigint unsigned | ID пути (FK → paths.id) |
| `allowed_mime` | string | MIME-тип (например, 'image/jpeg', 'video/mp4') |
| `created_at` | timestamp | Дата создания |
| `updated_at` | timestamp | Дата обновления |

### Индексы:

1. **`uq_path_media_constraints_path_mime`** - уникальный индекс `(path_id, allowed_mime)` - предотвращает дубликаты
2. **`idx_path_media_constraints_path_id`** - индекс `path_id` для быстрых запросов по path
3. **`idx_path_media_constraints_mime`** - индекс `allowed_mime` для обратных запросов

### Внешние ключи:

- **Исходящие:**
  - `path_id` → `paths.id` (CASCADE ON DELETE)

### Особенности:

- Используется только для полей типа `media`
- Определяет, какие MIME-типы допустимы для media-поля
- При удалении path автоматически удаляются все связанные constraints

---

## 6. Таблица `post_types`

**Назначение:** Хранит типы записей (например, 'article', 'page', 'post'). Каждый тип может быть связан с blueprint, определяющим структуру Entry.

### Структура полей:

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint unsigned | Первичный ключ |
| `name` | string | Название типа записи |
| `template` | string nullable | Шаблон |
| `options_json` | json nullable | Дополнительные опции в JSON |
| `blueprint_id` | bigint unsigned nullable | Связанный blueprint (FK → blueprints.id) |
| `created_at` | timestamp | Дата создания |
| `updated_at` | timestamp | Дата обновления |

### Индексы:

- **`idx_post_types_blueprint`** - индекс `blueprint_id` (создается в миграции blueprints)

### Внешние ключи:

- **Исходящие:**
  - `blueprint_id` → `blueprints.id` (RESTRICT ON DELETE) - создается в миграции blueprints
- **Входящие:**
  - `path_ref_constraints.allowed_post_type_id` → `post_types.id` (RESTRICT ON DELETE)
  - `form_configs.post_type_id` → `post_types.id` (RESTRICT ON DELETE)

### Особенности:

- Blueprint может быть не назначен (`blueprint_id = NULL`)
- При удалении blueprint проверяется, что он не используется в PostType

---

## 7. Таблица `form_configs`

**Назначение:** Хранит конфигурации форм для комбинаций PostType и Blueprint.

### Структура полей:

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint unsigned | Первичный ключ |
| `post_type_id` | bigint unsigned | ID типа записи (FK → post_types.id) |
| `blueprint_id` | bigint unsigned | ID blueprint (FK → blueprints.id) |
| `config_json` | json | Конфигурация формы в JSON |
| `created_at` | timestamp | Дата создания |
| `updated_at` | timestamp | Дата обновления |

### Индексы:

1. **`uq_form_configs_post_type_blueprint`** - уникальный составной индекс `(post_type_id, blueprint_id)` - одна конфигурация на комбинацию
2. **`post_type_id`** - индекс для быстрого поиска по PostType
3. **`blueprint_id`** - индекс для быстрого поиска по Blueprint

### Внешние ключи:

- **Исходящие:**
  - `post_type_id` → `post_types.id` (RESTRICT ON DELETE)
  - `blueprint_id` → `blueprints.id` (CASCADE ON DELETE)

### Особенности:

- Одна конфигурация формы на комбинацию PostType + Blueprint
- При удалении blueprint автоматически удаляются связанные конфигурации форм

---

## Диаграмма связей

```
blueprints (1) ──< (N) paths
  │                    │
  │                    ├──< (N) path_ref_constraints
  │                    │
  │                    └──< (N) path_media_constraints
  │
  ├──< (N) blueprint_embeds (N) >──┐
  │                                 │
  │                                 │
  └──< (N) post_types               │
       │                            │
       └──< (N) form_configs         │
                                    │
paths (1) ──< (N) paths (self-ref)  │
  │                                  │
  └──< (N) blueprint_embeds.host_path_id
```

---

## Ключевые особенности архитектуры

### 1. Материализация путей

- `full_path` материализуется при создании/копировании (не вычисляется на лету)
- Для MySQL используется префикс индекса (766 символов) из-за лимита длины ключа (3072 байта)

### 2. Система копирования

- **Собственные поля:** `blueprint_embed_id = NULL`
- **Скопированные поля:** имеют `blueprint_embed_id`, исходный blueprint доступен через `blueprintEmbed->embeddedBlueprint`
- Скопированные поля защищены от прямого редактирования/удаления

### 3. Каскадные удаления

- Удаление blueprint → удаление всех его paths (CASCADE)
- Удаление embed → удаление всех скопированных paths (CASCADE)
- Удаление path → удаление дочерних paths (CASCADE)
- Удаление path → удаление constraints (CASCADE)

### 4. Ограничения на удаление

- Blueprint нельзя удалить, если он используется в PostType (RESTRICT)
- Blueprint нельзя удалить, если он встроен в другие blueprint'ы (RESTRICT)
- PostType нельзя удалить, если используется в ref-constraints (RESTRICT)

### 5. Оптимизация производительности

- Множество индексов для оптимизации запросов материализации
- Составные индексы для частых паттернов запросов
- Префиксные индексы для длинных строк (MySQL)

---

## Примеры использования

### Пример 1: Простая структура

```sql
-- Blueprint "Address"
INSERT INTO blueprints (name, code) VALUES ('Address', 'address');
INSERT INTO paths (blueprint_id, name, full_path, data_type) 
VALUES (1, 'street', 'street', 'string');

-- Blueprint "Company" встраивает "Address"
INSERT INTO blueprints (name, code) VALUES ('Company', 'company');
INSERT INTO blueprint_embeds (blueprint_id, embedded_blueprint_id, host_path_id) 
VALUES (2, 1, NULL); -- встраивание в корень

-- Автоматически создается копия пути:
-- paths: blueprint_id=2, name='street', full_path='street', 
--        blueprint_embed_id=1
-- Исходный blueprint доступен через: blueprintEmbed->embeddedBlueprint (id=1)
```

### Пример 2: Встраивание под полем

```sql
-- Создать поле-группу в Company
INSERT INTO paths (blueprint_id, name, full_path, data_type) 
VALUES (2, 'office', 'office', 'json');

-- Встроить Address под полем office
INSERT INTO blueprint_embeds (blueprint_id, embedded_blueprint_id, host_path_id) 
VALUES (2, 1, 3); -- host_path_id указывает на поле 'office'

-- Автоматически создается копия пути:
-- paths: blueprint_id=2, name='street', full_path='office.street', 
--        blueprint_embed_id=2
-- Исходный blueprint доступен через: blueprintEmbed->embeddedBlueprint (id=1)
```

### Пример 3: Constraints для ref-поля

```sql
-- Создать ref-поле
INSERT INTO paths (blueprint_id, name, full_path, data_type) 
VALUES (2, 'author', 'author', 'ref');

-- Установить ограничение: author может ссылаться только на PostType "User"
INSERT INTO path_ref_constraints (path_id, allowed_post_type_id) 
VALUES (4, 1); -- post_type_id=1 это "User"
```

---

## Примечания по миграциям

1. **Порядок создания:** 
   - `post_types` создается раньше `blueprints`
   - `paths` создается раньше `blueprint_embeds`
   - Внешние ключи добавляются в отдельных миграциях

2. **Обработка отката:**
   - Миграции проверяют существование внешних ключей перед удалением
   - Используется `INFORMATION_SCHEMA` для проверки существования индексов и FK

3. **MySQL-специфичные оптимизации:**
   - Префиксные индексы для длинных строк
   - Проверка драйвера БД перед созданием специфичных индексов

