# План упрощения таблицы `doc_values`

## Анализ текущей структуры

### Текущие столбцы

```sql
id                  -- автоинкрементный первичный ключ
entry_id            -- FK → entries.id
path_id             -- FK → paths.id
cardinality         -- денормализованное из paths.cardinality
array_index         -- индекс в массиве (NULL для one, 1-based для many)
value_string        -- строка (до 500 символов)
value_int           -- целое число
value_float         -- число с плавающей точкой
value_bool          -- булево
value_date          -- дата
value_datetime      -- дата и время
value_text          -- текст (неограниченный)
value_json          -- JSON
created_at          -- timestamp создания
updated_at          -- timestamp обновления
```

## Выявленные избыточности

### 0. ✅ `value_date` — ИЗБЫТОЧЕН

**Причина:**

-   Дублирует функциональность `value_datetime`
-   Дата может храниться в `DATETIME` с временем 00:00:00
-   Для сравнения по дате можно использовать `DATE(value_datetime)`

**Текущее использование:**

-   Тип `date` → сохраняется в `value_date` как `Y-m-d` (строка)
-   Тип `datetime` → сохраняется в `value_datetime` как полный DateTime
-   Оба имеют индексы

**Преимущества удаления:**

-   Упрощение структуры (меньше колонок)
-   Унификация обработки дат
-   Меньше дублирования кода
-   Экономия места (~5 байт на запись: DATE = 3 байта, но DATETIME = 8 байт — в итоге может быть наоборот, но упрощение важнее)

**Недостатки:**

-   Небольшое увеличение размера (DATE = 3 байта, DATETIME = 8 байт, разница ~5 байт)
-   Нужно изменить логику индексации (date → сохранять в datetime с 00:00:00)

**Рекомендация:** ✅ **УДАЛИТЬ** — упрощение важнее небольшой экономии места.

---

### 1. ❌ `id` (автоинкрементный) — ИЗБЫТОЧЕН

**Причина:**

-   Уже существует уникальный индекс `(entry_id, path_id, array_index)`
-   Можно использовать как составной первичный ключ
-   Eloquent использует `$model->id` по умолчанию, но это решаемо через `$primaryKey` и `$incrementing = false`

**Преимущества удаления:**

-   Меньше размер записи (4-8 байт)
-   Быстрее INSERT (нет автоинкремента)
-   Логичнее использовать естественный ключ

**Недостатки:**

-   Требует изменений в коде (Eloquent, связи, тесты)
-   Составные ключи менее удобны в запросах

**Рекомендация:** ⚠️ **УСЛОВНО ИЗБЫТОЧЕН** — можно удалить, но требует рефакторинга.

---

### 2. ✅ `cardinality` — ИЗБЫТОЧЕН

**Причина:**

-   Денормализованное поле из `paths.cardinality`
-   Используется только для CHECK-констрейнта `chk_doc_values_array_index`
-   Можно получать через `JOIN paths ON paths.id = doc_values.path_id`

**Проверка использования:**

-   ❌ Не используется в запросах (только в индексации для сохранения)
-   ❌ Не используется в фильтрации
-   ❌ Не возвращается в API
-   ✅ Используется только в CHECK-констрейнте

**Преимущества удаления:**

-   Меньше размер записи
-   Меньше дублирование данных (нормализация)
-   Меньше риск рассинхронизации

**Недостатки:**

-   CHECK-констрейнт станет сложнее (подзапрос вместо прямой проверки)
-   Нужно пересмотреть констрейнты (возможно, убрать или упростить)

**Рекомендация:** ✅ **УДАЛИТЬ** — денормализация не оправдана.

---

### 3. ✅ `created_at` / `updated_at` — ИЗБЫТОЧНЫ

**Причина:**

-   Таблица полностью пересоздаётся при каждой индексации (`DELETE + INSERT`)
-   Timestamps не используются ни в одном запросе
-   Не возвращаются в API
-   Это индексная таблица, не бизнес-данные

**Проверка использования:**

-   ❌ Не используются в запросах
-   ❌ Не используются в сортировке
-   ❌ Не возвращаются в ресурсах
-   ❌ Не логируются

**Преимущества удаления:**

-   Меньше размер записи (2 × 8 байт = 16 байт на запись)
-   Быстрее INSERT (не нужно устанавливать timestamps)
-   Чище семантика (индексные данные не требуют версионирования)

**Недостатки:**

-   Нет отслеживания времени создания/изменения (но это не критично для индекса)

**Рекомендация:** ✅ **УДАЛИТЬ** — не используются.

---

### 4. ⚠️ Множество `value_*` колонок — НЕ ИЗБЫТОЧНЫ

**Почему оставить:**

-   Типобезопасные сравнения (`value_int > 100` vs JSON-парсинг)
-   Индексы по типам данных (быстрые запросы)
-   CHECK-констрейнт гарантирует одно значение
-   Производительность запросов критична для индексации

**Альтернатива (НЕ РЕКОМЕНДУЕТСЯ):**

-   Одна колонка `value` типа JSON/TEXT
-   ❌ Потеря производительности
-   ❌ Потеря типобезопасности
-   ❌ Сложнее индексы

**Рекомендация:** ✅ **ОСТАВИТЬ** — оптимизация запросов важнее.

---

## План реализации

### ⚠️ Критические замечания перед началом

#### 1. Баг в `orderByPath()` (критично)

**Проблема:**

-   В `app/Traits/HasDocumentData.php:155` используется hardcoded `value_string`
-   Это **не работает** для `text`-полей (они сохраняются в `value_text`)
-   Нужно исправить в Этапе 0

**Решение:**

-   Определять колонку по `data_type` из `paths`
-   Использовать `value_string` для `data_type='string'` и `value_text` для `data_type='text'`

#### 2. Порядок операций в миграциях

При удалении колонок и обновлении CHECK-констрейнтов порядок операций критичен:

1. Сначала DROP констрейнты, которые зависят от колонки
2. Потом мигрировать данные
3. Потом удалить колонку
4. Потом создать новые констрейнты

---

### Этап 0: Удаление `value_date` (НИЗКИЙ РИСК)

**Изменения:**

1. **Миграция:**

    ```php
    // database/migrations/YYYY_MM_DD_HHMMSS_remove_value_date_from_doc_values.php

    Порядок операций:
    1. DROP CHECK chk_doc_values_single_value (зависит от value_date)
    2. Мигрировать данные:
       UPDATE doc_values
       SET value_datetime = DATE_ADD(value_date, INTERVAL 0 SECOND)
       WHERE value_date IS NOT NULL
    3. Удалить колонку value_date
    4. Удалить индекс idx_value_date
    5. CREATE CHECK chk_doc_values_single_value (без value_date)

    Примечание:
    - DATE_ADD() безопаснее, чем CAST() для миграции
    - Для MySQL/PostgreSQL использовать соответствующие функции
    - Проверить поддержку CHECK-констрейнтов в других СУБД
    ```

2. **Код:**

    - `app/Services/Entry/EntryIndexer.php`:
        - В `getValueFieldForType()`: `'date' => 'value_datetime'` (вместо `'value_date'`)
        - В `castValue()`: для `'date'` сохранять DateTime с 00:00:00:
            ```php
            'date' => $value instanceof \DateTimeInterface
                ? $value->setTime(0, 0, 0)  // или $value->startOfDay()
                : (is_string($value) ? now()->parse($value)->startOfDay() : now()->startOfDay()),
            ```
    - `app/Models/DocValue.php` — удалить `value_date` из fillable и PHPDoc
    - `app/Traits/HasDocumentData.php`:
        - ❌ **КРИТИЧНО:** Исправить `orderByPath()` — определять колонку по `data_type` из `paths`
        - Обновить `detectValueField()` для учета `text`-типов (опционально)

3. **Исправление `orderByPath()`:**

    ```php
    // app/Traits/HasDocumentData.php

    public function scopeOrderByPath(Builder $query, string $fullPath, string $direction = 'asc'): Builder
    {
        return $query
            ->leftJoin('doc_values as dv_sort', function ($join) use ($fullPath) {
                $join->on('entries.id', '=', 'dv_sort.entry_id')
                    ->whereIn('dv_sort.path_id', function ($subQuery) use ($fullPath) {
                        $subQuery->select('id')
                            ->from('paths')
                            ->where('full_path', $fullPath);
                    });
            })
            ->leftJoin('paths as p_sort', 'p_sort.id', '=', 'dv_sort.path_id')
            ->orderByRaw("
                CASE
                    WHEN p_sort.data_type = 'text' THEN dv_sort.value_text
                    WHEN p_sort.data_type = 'string' THEN dv_sort.value_string
                    WHEN p_sort.data_type IN ('int', 'float', 'bool', 'date', 'datetime') THEN
                        CASE
                            WHEN p_sort.data_type = 'int' THEN CAST(dv_sort.value_int AS CHAR)
                            WHEN p_sort.data_type = 'float' THEN CAST(dv_sort.value_float AS CHAR)
                            WHEN p_sort.data_type = 'bool' THEN CAST(dv_sort.value_bool AS CHAR)
                            WHEN p_sort.data_type IN ('date', 'datetime') THEN dv_sort.value_datetime
                            ELSE NULL
                        END
                    ELSE dv_sort.value_string
                END
            ", $direction)
            ->select('entries.*');
    }

    // Или проще (если нужна только сортировка по string/text):
    ->orderByRaw("
        COALESCE(
            CASE WHEN p_sort.data_type = 'text' THEN dv_sort.value_text END,
            dv_sort.value_string
        )
    ", $direction)
    ```

4. **Тесты:**

    - Обновить тесты, которые проверяют `value_date`
    - Добавить тесты, что `date` сохраняется в `value_datetime` с 00:00:00
    - Добавить тесты сортировки по `text`-полям через `orderByPath()`
    - Проверить, что `wherePath()` корректно работает с `text`-полями

**Время:** ~2-3 часа (включая исправление бага)  
**Риск:** Низкий

---

### Этап 1: Удаление `cardinality` (НИЗКИЙ РИСК)

**Изменения:**

1. **Миграция:**

    ```php
    // database/migrations/YYYY_MM_DD_HHMMSS_remove_cardinality_from_doc_values.php

    Порядок операций:
    1. DROP CHECK chk_doc_values_array_index (зависит от cardinality)
    2. Удалить колонку cardinality
    3. (Опционально) CREATE CHECK chk_doc_values_single_value, если нужно обновить

    Примечание:
    - Констрейнт chk_doc_values_array_index удаляется (логика проверки в EntryIndexer)
    - Можно переписать через подзапрос, но это сложнее и не рекомендуется:
      CHECK (
          (array_index IS NULL AND (SELECT cardinality FROM paths WHERE id = path_id) = 'one') OR
          (array_index IS NOT NULL AND (SELECT cardinality FROM paths WHERE id = path_id) = 'many')
      )
    ```

2. **Код:**

    - `app/Models/DocValue.php` — удалить `cardinality` из fillable и PHPDoc
    - `app/Services/Entry/EntryIndexer.php` — не записывать `cardinality`:
        ```php
        // Удалить строку:
        'cardinality' => $path->cardinality,
        ```
    - `app/Traits/HasDocumentData.php` — при необходимости использовать JOIN для получения cardinality (если понадобится в будущем)
    - Проверить все места, где используется `$docValue->cardinality` (должно быть удалено)

3. **Тесты:**

    - Обновить проверки, которые используют `cardinality`
    - Добавить тесты на корректность работы без `cardinality`
    - Проверить, что логика проверки `array_index` работает в `EntryIndexer`

**Время:** ~2-3 часа  
**Риск:** Низкий

---

### Этап 2: Удаление `created_at` / `updated_at` (НИЗКИЙ РИСК)

**Изменения:**

1. **Миграция:**

    ```php
    // database/migrations/YYYY_MM_DD_HHMMSS_remove_timestamps_from_doc_values.php

    Порядок операций:
    1. Удалить колонки created_at, updated_at

    Примечание:
    - Timestamps не используются в запросах, безопасно удалить
    - Eloquent автоматически не будет пытаться устанавливать их после отключения в модели
    ```

2. **Код:**

    - `app/Models/DocValue.php` — добавить:
        ```php
        public $timestamps = false;
        ```
    - Убедиться, что Eloquent не пытается устанавливать timestamps
    - Проверить, что `EntryIndexer::index()` не использует timestamps

3. **Тесты:**

    - Проверить, что индексация работает корректно
    - Убедиться, что запросы к `doc_values` работают без timestamps

**Время:** ~1 час  
**Риск:** Низкий

---

### Этап 3: Замена `id` на составной ключ (СРЕДНИЙ РИСК, ОПЦИОНАЛЬНО)

**Изменения:**

1. **Миграция:**

    ```php
    // database/migrations/YYYY_MM_DD_HHMMSS_replace_id_with_composite_key_in_doc_values.php

    Порядок операций:
    1. Удалить автоинкрементный id
    2. Создать составной первичный ключ (entry_id, path_id, array_index)
    3. Уникальный индекс uq_doc_values_entry_path_idx станет первичным ключом

    Примечание:
    - Убедиться, что нет внешних ключей, ссылающихся на id
    - Проверить все места, где используется DocValue::find($id)
    ```

2. **Код:**

    - `app/Models/DocValue.php`:
        ```php
        public $incrementing = false;
        protected $primaryKey = ['entry_id', 'path_id', 'array_index'];
        protected $keyType = 'array';
        ```
    - Проверить все места, где используется:
        - `$docValue->id` → заменить на `[$docValue->entry_id, $docValue->path_id, $docValue->array_index]`
        - `DocValue::find($id)` → заменить на `DocValue::where([...])->first()`
        - `DocValue::whereKey($id)` → использовать составной ключ
    - Проверить связи в других моделях (если есть)
    - Обновить тесты, которые используют `id`

3. **Проверка:**

    - Все запросы через `where` по составному ключу
    - Все связи работают корректно
    - `EntryIndexer` не использует `id` (проверить)
    - Eloquent корректно работает с составным ключом

**Время:** ~3-4 часа  
**Риск:** Средний (требует тщательного тестирования)

---

## Итоговая структура после упрощения

```sql
CREATE TABLE doc_values (
    entry_id INT NOT NULL,
    path_id INT NOT NULL,
    array_index INT NULL,

    -- Одно значение заполнено (CHECK-констрейнт)
    value_string VARCHAR(500) NULL,
    value_int BIGINT NULL,
    value_float DOUBLE NULL,
    value_bool BOOLEAN NULL,
    value_datetime DATETIME NULL,  -- Используется для date и datetime
    value_text TEXT NULL,
    value_json JSON NULL,

    PRIMARY KEY (entry_id, path_id, array_index),  -- Если выполнен Этап 3
    -- или остаётся id, если Этап 3 не выполнен

    FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE,
    FOREIGN KEY (path_id) REFERENCES paths(id) ON DELETE CASCADE,

    INDEX idx_path_id (path_id),
    INDEX idx_value_string (value_string),
    INDEX idx_value_int (value_int),
    INDEX idx_value_float (value_float),
    INDEX idx_value_bool (value_bool),
    INDEX idx_value_datetime (value_datetime),

    -- CHECK-констрейнт: ровно одно value_* заполнено
    CONSTRAINT chk_doc_values_single_value CHECK (
        (value_string IS NOT NULL) +
        (value_int IS NOT NULL) +
        (value_float IS NOT NULL) +
        (value_bool IS NOT NULL) +
        (value_datetime IS NOT NULL) +
        (value_text IS NOT NULL) +
        (value_json IS NOT NULL) = 1
    )

    -- Примечание: chk_doc_values_array_index удалён (логика в EntryIndexer)
);

-- cardinality получается через JOIN:
-- SELECT dv.*, p.cardinality
-- FROM doc_values dv
-- JOIN paths p ON p.id = dv.path_id
```

## Экономия места

**До:**

-   `id`: 4-8 байт
-   `cardinality`: 1-4 байта (enum/string)
-   `value_date`: 3 байта (DATE, только если используется)
-   `created_at`: 8 байт
-   `updated_at`: 8 байт
-   **Итого:** ~24-31 байт на запись (если есть value_date)

**После (Этапы 0-2):**

-   `value_datetime` вместо `value_date`: +5 байт (8 вместо 3)
-   Удалены `cardinality`, `created_at`, `updated_at`: -17-20 байт
-   **Экономия:** ~12-15 байт на запись

**После (с Этапом 3):**

-   Дополнительно удалён `id`: -4-8 байт
-   **Общая экономия:** ~16-23 байта на запись

**Для 1 млн записей:**

-   Без Этапа 3: ~12-15 МБ
-   С Этапом 3: ~16-23 МБ

## Порядок выполнения

1. ✅ **Этап 0** — удаление `value_date` + исправление бага в `orderByPath()` (низкий риск, ~2-3 часа) — **НАЧАТЬ С ЭТОГО**
2. ✅ **Этап 1** — удаление `cardinality` (низкий риск, ~2-3 часа)
3. ✅ **Этап 2** — удаление timestamps (низкий риск, ~1 час)
4. ⚠️ **Этап 3** — замена `id` (опционально, средний риск, ~3-4 часа)

**Рекомендация:** Выполнить Этапы 0, 1 и 2 сразу, Этап 3 — по желанию (требует больше рефакторинга).

**Общее время (Этапы 0-2):** ~5-7 часов  
**Общее время (с Этапом 3):** ~8-11 часов

## Подготовка перед выполнением

### Обязательные проверки:

1. **Резервная копия БД:**

    ```bash
    # Создать резервную копию перед началом
    mysqldump -u user -p database_name > backup_before_refactoring.sql
    ```

2. **Проверить количество записей:**

    ```sql
    SELECT COUNT(*) FROM doc_values;
    SELECT COUNT(DISTINCT entry_id) FROM doc_values;
    SELECT COUNT(*) FROM doc_values WHERE value_date IS NOT NULL;
    SELECT COUNT(*) FROM doc_values WHERE value_string IS NOT NULL;
    SELECT COUNT(*) FROM doc_values WHERE value_text IS NOT NULL;
    ```

3. **Проверить использование колонок:**

    ```sql
    -- Проверить использование value_date
    SELECT COUNT(*) FROM doc_values WHERE value_date IS NOT NULL;

    -- Проверить использование cardinality
    SELECT cardinality, COUNT(*) FROM doc_values GROUP BY cardinality;

    -- Проверить использование timestamps (должны быть NULL или не использоваться)
    SELECT COUNT(*) FROM doc_values WHERE created_at IS NOT NULL;
    ```

4. **Запустить тесты на staging:**
    ```bash
    php artisan test
    ```

### Проверка совместимости СУБД:

-   Проверить поддержку CHECK-констрейнтов в используемой СУБД
-   MySQL 8.0+ поддерживает CHECK-констрейнты
-   SQLite поддерживает CHECK-констрейнты, но синтаксис может отличаться
-   PostgreSQL поддерживает CHECK-констрейнты

**Рекомендация:** Протестировать миграции на тестовой БД перед применением в production.

## Чек-лист выполнения

### Подготовка:

-   [ ] Создать резервную копию БД
-   [ ] Проверить количество записей в `doc_values`
-   [ ] Проверить использование `value_date`, `cardinality`, timestamps
-   [ ] Запустить тесты на staging окружении
-   [ ] Проверить совместимость СУБД с CHECK-констрейнтами

### Этап 0: Удаление `value_date` + исправление бага

-   [ ] Написать миграцию (удаление value_date с правильным порядком операций)
-   [ ] Обновить `EntryIndexer::getValueFieldForType()` (date → datetime)
-   [ ] Обновить `EntryIndexer::castValue()` (date с 00:00:00)
-   [ ] Обновить модель `DocValue` (удалить value_date из fillable/PHPDoc)
-   [ ] **КРИТИЧНО:** Исправить `HasDocumentData::orderByPath()` (определять колонку по data_type)
-   [ ] Обновить тесты для `value_date`
-   [ ] Добавить тесты для сортировки по `text`-полям
-   [ ] Запустить тесты
-   [ ] Проверить работу `orderByPath()` с разными типами данных

### Этап 1: Удаление `cardinality`

-   [ ] Написать миграцию (удаление cardinality с правильным порядком операций)
-   [ ] Обновить модель `DocValue` (удалить cardinality из fillable/PHPDoc)
-   [ ] Обновить `EntryIndexer::indexValuePath()` (не записывать cardinality)
-   [ ] Удалить CHECK-констрейнт `chk_doc_values_array_index`
-   [ ] Обновить тесты (убрать проверки cardinality)
-   [ ] Запустить тесты
-   [ ] Проверить, что логика проверки array_index работает в EntryIndexer

### Этап 2: Удаление timestamps

-   [ ] Написать миграцию (удаление created_at/updated_at)
-   [ ] Обновить модель `DocValue` (добавить `public $timestamps = false;`)
-   [ ] Проверить, что `EntryIndexer` не использует timestamps
-   [ ] Запустить тесты
-   [ ] Проверить производительность INSERT операций

### Этап 3: Замена `id` (опционально)

-   [ ] Написать миграцию (замена id на составной ключ)
-   [ ] Обновить модель `DocValue` (incrementing = false, primaryKey = [...])
-   [ ] Найти все использования `$docValue->id` и `DocValue::find($id)`
-   [ ] Заменить на работу с составным ключом
-   [ ] Проверить связи в других моделях
-   [ ] Обновить тесты
-   [ ] Запустить тесты
-   [ ] Проверить производительность запросов

### Финальные проверки:

-   [ ] Запустить полный набор тестов: `php artisan test`
-   [ ] Проверить производительность запросов к `doc_values`
-   [ ] Проверить, что индексы работают корректно
-   [ ] Обновить документацию
-   [ ] Запустить `composer scribe:gen`
-   [ ] Запустить `php artisan docs:generate`

## Дополнительные соображения

### Почему не объединять `value_*` в одну колонку?

1. **Производительность:**

    - Типобезопасные сравнения быстрее JSON-парсинга
    - Индексы работают эффективнее на нативных типах

2. **Простота запросов:**

    ```sql
    -- Сейчас (быстро, с индексом):
    WHERE value_int > 100

    -- С JSON (медленно, сложнее индексация):
    WHERE CAST(JSON_EXTRACT(value, '$.int') AS SIGNED) > 100
    ```

3. **CHECK-констрейнты:**
    - Проще проверить, что заполнено ровно одно поле

### Альтернатива: частичное упрощение

Можно оставить только часто используемые типы:

-   `value_string`, `value_int`, `value_float`, `value_datetime`
-   Остальные (`value_bool`, `value_text`, `value_json`) — в одну JSON колонку `value_other`

Но это усложнит код без существенной выгоды.

## Известные проблемы и ограничения

### 1. Баг в `orderByPath()` (исправляется в Этапе 0)

**Проблема:** Используется hardcoded `value_string`, не работает для `text`-полей.

**Решение:** Определять колонку по `data_type` из `paths`.

### 2. `detectValueField()` не учитывает `text`-тип

**Проблема:** По умолчанию возвращает `value_string`.

**Решение:** При необходимости добавить JOIN с `paths` для определения типа, или принимать `data_type` как параметр.

### 3. CHECK-констрейнты работают только для MySQL

**Проблема:** В текущей реализации CHECK-констрейнты добавляются только для MySQL.

**Решение:** Проверить поддержку в других СУБД перед выполнением, адаптировать миграции при необходимости.

## Оценка рисков

### Низкий риск (Этапы 0-2):

-   ✅ Минимальные изменения в логике
-   ✅ Чёткие шаги миграции
-   ✅ Легко откатить при проблемах
-   ✅ Исправление известного бага (`orderByPath()`)

### Средний риск (Этап 3):

-   ⚠️ Требует изменений в коде (Eloquent, связи)
-   ⚠️ Составные ключи менее удобны
-   ⚠️ Требует тщательного тестирования
-   ⚠️ Может затронуть другие части системы

**Рекомендация:** Выполнить Этапы 0-2 сначала, оценить результаты, затем решить о необходимости Этапа 3.

---

**Документ готов к использованию.** Все замечания из рецензии включены в план.
