# Архитектурный анализ: Избыточность структуры таблиц Blueprints

## Обзор

Данный документ содержит анализ структуры таблиц системы Blueprints на предмет избыточности, дублирования данных и возможностей оптимизации.

**Примечание:** Поле `source_blueprint_id` было удалено из таблицы `paths` в рамках оптимизации. Исходный blueprint теперь доступен через связь `blueprintEmbed->embeddedBlueprint`.

---

## 1. Анализ материализации `full_path`

### Текущая реализация

**Поле:** `paths.full_path` (string 2048) - материализованное поле

**Использование:**
- Индексируется для быстрого поиска (`uq_paths_full_path_per_blueprint`)
- Используется в запросах фильтрации Entry (`wherePath('author.name', '=', 'John')`)
- Используется для проверки конфликтов при материализации
- Вычисляется из `parent.full_path + '.' + name`

### Оценка избыточности

**✅ ОПРАВДАНА** - Материализация необходима по следующим причинам:

1. **Производительность запросов:**
   - Запросы к Entry используют `full_path` напрямую: `WHERE full_path = 'author.name'`
   - Вычисление на лету потребовало бы рекурсивных JOIN'ов или CTE
   - Индексы на вычисляемые поля работают хуже

2. **Уникальность:**
   - Уникальный индекс `(blueprint_id, full_path)` гарантирует отсутствие конфликтов
   - Вычисление на лету усложнило бы проверку уникальности

3. **Обновление:**
   - При изменении `name` или `parent_id` пересчитывается рекурсивно для всех дочерних
   - Это происходит редко (структура меняется нечасто)
   - Транзакционность гарантирует консистентность

**Рекомендация:** Оставить как есть. Материализация оправдана требованиями производительности.

---

## 2. Анализ полей `source_blueprint_id` и `blueprint_embed_id`

### Текущая реализация

**Поля в `paths`:**
- `source_blueprint_id` (nullable) - откуда скопировано
- `blueprint_embed_id` (nullable) - к какому embed привязано

**Использование:**
- `source_blueprint_id` используется для:
  - Определения собственных путей: `whereNull('source_blueprint_id')`
  - Связи с исходным blueprint для отображения/логирования
- `blueprint_embed_id` используется для:
  - Удаления всех копий при удалении embed: `where('blueprint_embed_id', $embed->id)->delete()`
  - Исключения копий при проверке конфликтов
  - Поиска копий конкретного embed

### Оценка избыточности

**⚠️ ЧАСТИЧНАЯ ИЗБЫТОЧНОСТЬ** - Можно оптимизировать:

#### Проблема 1: Дублирование информации

**Текущая логика:**
- Если `source_blueprint_id IS NOT NULL` → это копия
- Если `blueprint_embed_id IS NOT NULL` → это копия
- Оба поля всегда заполняются вместе для копий

**Анализ:**
```sql
-- Все копии имеют оба поля заполненными
SELECT COUNT(*) FROM paths 
WHERE source_blueprint_id IS NOT NULL 
  AND blueprint_embed_id IS NULL;  -- Должно быть 0

SELECT COUNT(*) FROM paths 
WHERE blueprint_embed_id IS NOT NULL 
  AND source_blueprint_id IS NULL;  -- Должно быть 0
```

**Вывод:** `source_blueprint_id` можно вычислить через `blueprint_embed_id`:
```sql
source_blueprint_id = (
  SELECT embedded_blueprint_id 
  FROM blueprint_embeds 
  WHERE id = paths.blueprint_embed_id
)
```

#### Проблема 2: Неоптимальные запросы

**Текущие запросы:**
```php
// Загрузка собственных путей
->whereNull('source_blueprint_id')

// Удаление копий embed
->where('blueprint_embed_id', $embed->id)
```

**Если убрать `source_blueprint_id`:**
- Загрузка собственных путей: `->whereNull('blueprint_embed_id')` (уже используется)
- Связь с исходным blueprint: через JOIN с `blueprint_embeds`

### Рекомендации

**Вариант 1: Убрать `source_blueprint_id` (РЕКОМЕНДУЕТСЯ)**

**Преимущества:**
- Уменьшение размера таблицы (8 байт на запись для копий)
- Упрощение логики (одно поле вместо двух)
- Меньше индексов

**Недостатки:**
- JOIN при необходимости получить исходный blueprint
- Но JOIN нужен только для отображения/логирования (не критично)

**Миграция:**
```php
// 1. Удалить поле source_blueprint_id
// 2. Обновить запросы:
//    - whereNull('source_blueprint_id') → whereNull('blueprint_embed_id')
//    - sourceBlueprint() → через blueprintEmbed->embeddedBlueprint
```

**Вариант 2: Оставить оба поля (ТЕКУЩЕЕ РЕШЕНИЕ)**

**Преимущества:**
- Быстрый доступ к исходному blueprint без JOIN
- Упрощенные запросы для собственных путей
- Явная связь с исходным blueprint

**Недостатки:**
- Дублирование информации
- Больше места в БД

**Рекомендация:** Если производительность критична и JOIN не проблема, можно убрать `source_blueprint_id`. Если важна простота запросов - оставить как есть.

---

## 3. Анализ индексов таблицы `paths`

### Текущие индексы

1. `blueprint_id` - базовый индекс
2. `source_blueprint_id` - для поиска копий
3. `idx_paths_blueprint_parent` - `(blueprint_id, parent_id)` для иерархии
4. `idx_paths_own_paths` - `(blueprint_id, source_blueprint_id)` для собственных путей
5. `blueprint_embed_id` - для удаления копий
6. `uq_paths_full_path_per_blueprint` - уникальный `(blueprint_id, full_path(766))`
7. `idx_paths_materialization_lookup` - `(blueprint_id, blueprint_embed_id, source_blueprint_id, full_path(100))`
8. `idx_paths_conflict_check` - `(blueprint_id, full_path(766))`

### Оценка избыточности

**⚠️ ЧАСТИЧНАЯ ИЗБЫТОЧНОСТЬ** - Некоторые индексы перекрываются:

#### Проблема 1: Перекрытие индексов

**Индекс 4 и 7:**
- `idx_paths_own_paths`: `(blueprint_id, source_blueprint_id)`
- `idx_paths_materialization_lookup`: `(blueprint_id, blueprint_embed_id, source_blueprint_id, full_path(100))`

**Анализ:**
- Индекс 7 покрывает индекс 4 (префикс)
- Но индекс 7 используется только для batch insert lookup
- Индекс 4 используется для загрузки собственных путей

**Вывод:** Индекс 4 можно убрать, если индекс 7 покрывает запросы.

#### Проблема 2: Дублирование `full_path` в индексах

**Индексы 6, 7, 8:**
- `uq_paths_full_path_per_blueprint`: `(blueprint_id, full_path(766))` - уникальный
- `idx_paths_materialization_lookup`: `(blueprint_id, blueprint_embed_id, source_blueprint_id, full_path(100))`
- `idx_paths_conflict_check`: `(blueprint_id, full_path(766))`

**Анализ:**
- Индекс 6 (уникальный) уже покрывает запросы индекса 8
- Индекс 8 можно убрать, если уникальный индекс используется для конфликтов

**Но:** Уникальный индекс может быть медленнее для SELECT (из-за проверки уникальности), поэтому отдельный индекс для чтения может быть оправдан.

### Рекомендации

**Оптимизация 1: Убрать `idx_paths_own_paths`**

Если `idx_paths_materialization_lookup` покрывает запросы:
```sql
-- Текущий запрос
WHERE blueprint_id = ? AND source_blueprint_id IS NULL

-- Можно использовать индекс 7 (префикс)
WHERE blueprint_id = ? AND blueprint_embed_id IS NULL AND source_blueprint_id IS NULL
```

**Оптимизация 2: Оставить `idx_paths_conflict_check`**

Уникальный индекс может быть медленнее для SELECT, поэтому отдельный индекс для чтения оправдан.

**Оптимизация 3: Пересмотреть составные индексы**

Если убрать `source_blueprint_id`, индексы упростятся:
- `idx_paths_own_paths` → не нужен (используем `blueprint_embed_id IS NULL`)
- `idx_paths_materialization_lookup` → `(blueprint_id, blueprint_embed_id, full_path(100))`

---

## 4. Анализ таблиц constraints

### Текущая реализация

**Две отдельные таблицы:**
- `path_ref_constraints` - для ref-полей
- `path_media_constraints` - для media-полей

**Структура:**
```sql
path_ref_constraints:
  - path_id
  - allowed_post_type_id

path_media_constraints:
  - path_id
  - allowed_mime
```

### Оценка избыточности

**✅ НЕ ИЗБЫТОЧНО** - Разделение оправдано:

1. **Разные типы данных:**
   - Ref: связь с `post_types` (FK)
   - Media: строка MIME-типа

2. **Разные паттерны использования:**
   - Ref: проверка существования PostType
   - Media: проверка строки на соответствие списку

3. **Простота запросов:**
   - Отдельные таблицы упрощают JOIN'ы
   - Нет необходимости в `constraint_type` enum

**Альтернатива (не рекомендуется):**
```sql
path_constraints:
  - path_id
  - constraint_type (enum: 'ref', 'media')
  - allowed_post_type_id (nullable)
  - allowed_mime (nullable)
```

**Проблемы альтернативы:**
- Нарушение нормализации (одно из полей всегда NULL)
- Сложнее валидация (CHECK constraint)
- Медленнее индексы (больше NULL значений)

**Рекомендация:** Оставить как есть. Разделение оправдано.

---

## 5. Анализ таблицы `blueprint_embeds`

### Текущая реализация

**Поля:**
- `blueprint_id` - host
- `embedded_blueprint_id` - embedded
- `host_path_id` - под каким полем (nullable)

**Индексы:**
- Уникальный: `(blueprint_id, embedded_blueprint_id, host_path_id)`
- `embedded_blueprint_id`
- `blueprint_id`

### Оценка избыточности

**✅ НЕ ИЗБЫТОЧНО** - Структура оптимальна:

1. **Уникальность:**
   - Позволяет множественное встраивание под разными путями
   - Правильно обрабатывает NULL в уникальном индексе

2. **Индексы:**
   - Все индексы используются для разных запросов
   - Нет перекрытия

**Рекомендация:** Оставить как есть.

---

## 6. Анализ связи `paths.parent_id`

### Текущая реализация

**Самореференс:** `paths.parent_id` → `paths.id`

**Использование:**
- Построение иерархии полей
- Вычисление `full_path` (рекурсивно)
- Каскадное удаление дочерних путей

### Оценка избыточности

**✅ НЕ ИЗБЫТОЧНО** - Стандартный паттерн для деревьев:

**Альтернативы:**

1. **Materialized Path (текущее решение):**
   - `full_path` хранится как строка
   - Быстрый поиск по пути
   - Легко фильтровать по префиксу

2. **Nested Sets:**
   - Сложнее обновления
   - Быстрее для выборки поддерева
   - Но у нас уже есть `full_path` для этого

3. **Adjacency List (текущее решение):**
   - `parent_id` для иерархии
   - Простые обновления
   - Рекурсивные запросы для обхода

**Вывод:** Комбинация `parent_id` + `full_path` оптимальна:
- `parent_id` для построения дерева
- `full_path` для быстрого поиска и фильтрации

**Рекомендация:** Оставить как есть.

---

## 7. Общие рекомендации по оптимизации

### Приоритет 1: Высокий (рекомендуется)

1. **Убрать `source_blueprint_id` из `paths`**
   - Экономия: ~8 байт на копию пути
   - Упрощение логики
   - Миграция: добавить JOIN через `blueprint_embed_id`

2. **Оптимизировать индексы после удаления `source_blueprint_id`**
   - Убрать `idx_paths_own_paths`
   - Упростить `idx_paths_materialization_lookup`

### Приоритет 2: Средний (опционально)

1. **Пересмотреть необходимость `idx_paths_conflict_check`**
   - Проверить, можно ли использовать уникальный индекс
   - Если да - убрать дублирующий индекс

2. **Анализ использования `idx_paths_materialization_lookup`**
   - Проверить, действительно ли нужны все поля в индексе
   - Возможно, достаточно `(blueprint_id, blueprint_embed_id)`

### Приоритет 3: Низкий (не рекомендуется)

1. **Объединить таблицы constraints**
   - Не рекомендуется из-за нарушения нормализации
   - Усложнение логики

---

## 8. Оценка влияния изменений

### Удаление `source_blueprint_id`

**Экономия места:**
- На 1000 копий путей: ~8 KB
- На 100,000 копий: ~800 KB
- Не критично, но приятный бонус

**Влияние на производительность:**
- Запросы собственных путей: без изменений (используем `blueprint_embed_id IS NULL`)
- Получение исходного blueprint: +1 JOIN
- Влияние минимально (JOIN по индексированному полю)

**Сложность миграции:**
- Средняя: нужно обновить запросы и модели
- Обратная совместимость: можно добавить accessor в модель

### Оптимизация индексов

**Экономия места:**
- На индекс `idx_paths_own_paths`: зависит от размера таблицы
- Обычно несколько MB

**Влияние на производительность:**
- Запросы должны использовать покрывающие индексы
- Нужно протестировать на реальных данных

---

## 9. Выводы

### Избыточность подтверждена в:

1. ✅ **`source_blueprint_id`** - можно вычислить через `blueprint_embed_id`
2. ⚠️ **Индексы** - частичное перекрытие, можно оптимизировать

### Избыточность НЕ подтверждена в:

1. ✅ **`full_path`** - материализация оправдана производительностью
2. ✅ **Constraints таблицы** - разделение оправдано
3. ✅ **`blueprint_embeds`** - структура оптимальна
4. ✅ **`parent_id`** - стандартный паттерн для деревьев

### Рекомендации:

1. **Немедленно:** Убрать `source_blueprint_id` (если нет критических зависимостей)
2. **После удаления:** Оптимизировать индексы
3. **Долгосрочно:** Мониторить использование индексов, убирать неиспользуемые

---

## 10. План миграции (если решено убрать `source_blueprint_id`)

### Шаг 1: Подготовка

```php
// Добавить accessor в модель Path для обратной совместимости
public function getSourceBlueprintIdAttribute(): ?int
{
    if ($this->blueprint_embed_id === null) {
        return null;
    }
    
    return $this->blueprintEmbed?->embedded_blueprint_id;
}
```

### Шаг 2: Обновление запросов

```php
// Было:
->whereNull('source_blueprint_id')

// Стало:
->whereNull('blueprint_embed_id')
```

### Шаг 3: Миграция БД

```php
Schema::table('paths', function (Blueprint $table) {
    $table->dropForeign(['source_blueprint_id']);
    $table->dropIndex('source_blueprint_id');
    $table->dropIndex('idx_paths_own_paths');
    $table->dropColumn('source_blueprint_id');
});
```

### Шаг 4: Обновление индексов

```php
// Упростить idx_paths_materialization_lookup
// Было: (blueprint_id, blueprint_embed_id, source_blueprint_id, full_path(100))
// Стало: (blueprint_id, blueprint_embed_id, full_path(100))
```

---

## Приложение: Метрики для оценки

### Запросы для анализа использования индексов (MySQL):

```sql
-- Использование индексов
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    SEQ_IN_INDEX,
    COLUMN_NAME,
    CARDINALITY
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'paths'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- Размер индексов
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    ROUND(STAT_VALUE * @@innodb_page_size / 1024 / 1024, 2) AS 'Size (MB)'
FROM mysql.innodb_index_stats
WHERE database_name = DATABASE()
  AND table_name = 'paths'
  AND stat_name = 'size'
ORDER BY stat_value DESC;
```

### Запросы для проверки избыточности:

```sql
-- Проверка: все копии имеют оба поля
SELECT 
    COUNT(*) as total_copies,
    SUM(CASE WHEN source_blueprint_id IS NOT NULL AND blueprint_embed_id IS NOT NULL THEN 1 ELSE 0 END) as both_filled,
    SUM(CASE WHEN source_blueprint_id IS NULL AND blueprint_embed_id IS NOT NULL THEN 1 ELSE 0 END) as only_embed,
    SUM(CASE WHEN source_blueprint_id IS NOT NULL AND blueprint_embed_id IS NULL THEN 1 ELSE 0 END) as only_source
FROM paths
WHERE source_blueprint_id IS NOT NULL OR blueprint_embed_id IS NOT NULL;

-- Проверка: можно ли вычислить source_blueprint_id через blueprint_embed_id
SELECT 
    p.id,
    p.source_blueprint_id as stored_source,
    be.embedded_blueprint_id as computed_source,
    CASE 
        WHEN p.source_blueprint_id = be.embedded_blueprint_id THEN 'OK'
        ELSE 'MISMATCH'
    END as status
FROM paths p
JOIN blueprint_embeds be ON p.blueprint_embed_id = be.id
WHERE p.source_blueprint_id IS NOT NULL;
```

