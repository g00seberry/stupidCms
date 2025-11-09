# ADR-0004: Closure Table for Term Hierarchy

**Status**: Accepted  
**Date**: 2025-11-08  
**Deciders**: @backend-team  

## Context

Taxonomy terms должны поддерживать иерархическую структуру:
- Категория → Подкатегория → Подподкатегория
- Быстрые запросы: "все дочерние", "все родительские", "путь до корня"
- Произвольная глубина вложенности
- Производительность на 10k+ терминах

Пример:
```
Технологии (id: 1)
├── Backend (id: 2)
│   ├── PHP (id: 3)
│   └── Python (id: 4)
└── Frontend (id: 5)
    └── React (id: 6)
```

## Decision

Использовать паттерн **Closure Table** (таблица замыканий) для хранения иерархии.

**Структура**:

```sql
-- terms: базовая таблица
terms: { id, taxonomy_id, slug, name, description }

-- term_tree: таблица замыканий
term_tree: {
  term_id,      -- дочерний узел
  parent_id,    -- родительский узел
  level,        -- уровень вложенности (0 = сам в себя)
  path          -- полный путь "1/2/3"
}
PRIMARY KEY (term_id, parent_id)
```

**Пример данных** для дерева выше:

| term_id | parent_id | level | path      |
|---------|-----------|-------|-----------|
| 1       | 1         | 0     | 1         |
| 2       | 1         | 1     | 1/2       |
| 2       | 2         | 0     | 2         |
| 3       | 1         | 2     | 1/2/3     |
| 3       | 2         | 1     | 2/3       |
| 3       | 3         | 0     | 3         |
| 4       | 1         | 2     | 1/2/4     |
| 4       | 2         | 1     | 2/4       |
| 4       | 4         | 0     | 4         |

**Запросы**:
```sql
-- Все дочерние PHP (id=3)
SELECT t.* FROM terms t
JOIN term_tree tt ON tt.term_id = t.id
WHERE tt.parent_id = 3 AND tt.level > 0;

-- Все родительские PHP (id=3)
SELECT t.* FROM terms t
JOIN term_tree tt ON tt.parent_id = t.id
WHERE tt.term_id = 3 AND tt.level > 0;

-- Прямые дети Backend (id=2)
SELECT t.* FROM terms t
JOIN term_tree tt ON tt.term_id = t.id
WHERE tt.parent_id = 2 AND tt.level = 1;
```

## Alternatives Considered

### 1. Adjacency List (parent_id в terms)
```sql
terms: { id, parent_id, name }
```
**Плюсы**: Простота, стандартный подход  
**Минусы**:
- Медленные рекурсивные запросы (WITH RECURSIVE)
- Сложно получить всех потомков за 1 запрос
- Проблемы с производительностью на глубоких деревьях

### 2. Nested Sets (left/right boundaries)
```sql
terms: { id, lft, rgt, name }
```
**Плюсы**: Быстрое чтение поддерева  
**Минусы**:
- Медленные вставки (пересчёт lft/rgt для всего дерева)
- Сложная логика обновлений
- Хрупкость данных (легко сломать целостность)

### 3. Materialized Path
```sql
terms: { id, path: '/1/2/3/', name }
```
**Плюсы**: Простота, читаемость  
**Минусы**:
- Ограничение длины пути (VARCHAR limit)
- Сложные запросы при изменении иерархии
- Нет FK для целостности

### 4. Полиморфные отношения Laravel
```php
$term->children(), $term->parent()
```
**Минусы**:
- N+1 queries
- Медленно на больших деревьях
- Ручной traverse в коде

## Consequences

### Положительные
- ✅ Быстрые запросы (1 JOIN для любой глубины)
- ✅ Простые INSERT/UPDATE (нет пересчёта границ)
- ✅ FK constraints для целостности
- ✅ Легко получить путь до корня (`path` column)
- ✅ Поддержка Multiple Classification (термин может быть в нескольких родителях)

### Отрицательные
- ❌ Больше записей в БД (O(N²) для глубоких деревьев)
- ❌ Сложнее логика вставки (нужны триггеры или сервис)
- ❌ Дублирование данных (path хранится избыточно)

### Нейтральные
- ⚠️ Требуется сервис для управления деревом
- ⚠️ При удалении узла — каскадное удаление потомков
- ⚠️ `level=0` — ссылка на самого себя (self-reference)

## Implementation

**Файлы**:
- `app/Models/Term.php`
- `app/Models/TermTree.php`
- `database/migrations/*_create_term_tree_table.php`

**Связи**:
```php
// app/Models/Term.php
public function children() {
    return $this->hasManyThrough(
        Term::class, TermTree::class,
        'parent_id', 'id', 'id', 'term_id'
    )->where('term_tree.level', 1);
}

public function ancestors() {
    return $this->hasManyThrough(
        Term::class, TermTree::class,
        'term_id', 'id', 'id', 'parent_id'
    )->where('term_tree.level', '>', 0);
}
```

**Вставка узла**:
```php
// Добавить PHP (id=3) как дочерний Backend (id=2)
// 1. Копируем все связи родителя
INSERT INTO term_tree (term_id, parent_id, level, path)
SELECT 3, parent_id, level+1, CONCAT(path, '/3')
FROM term_tree WHERE term_id = 2;

// 2. Добавляем self-reference
INSERT INTO term_tree (term_id, parent_id, level, path)
VALUES (3, 3, 0, '3');
```

## Related

- Документация: [Taxonomy](../../10-concepts/taxonomy.md)
- Тесты: `tests/Feature/Admin/Terms/CrudTermsTest.php`

## References

- Bill Karwin, "SQL Antipatterns" (Chapter 3: Naive Trees)
- Vadim Tropashko, "SQL Design Patterns" (Nested Sets vs Closure Table)

