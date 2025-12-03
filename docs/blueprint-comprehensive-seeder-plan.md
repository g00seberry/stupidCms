# План создания комплексного сидера для Blueprint

## Цель
Создать максимально полный сидер, покрывающий все типы данных, кардинальности и уровни вложенности для тестирования и демонстрации возможностей системы Blueprint.

---

## Этап 1: Основная структура (текущая задача)

### Blueprint: `comprehensive_types`

**Структура:**

#### Уровень 0: Корень
- Все типы с `cardinality = one`, названия: `simple_{data_type}`
  - `simple_string` (string, one)
  - `simple_text` (text, one)
  - `simple_int` (int, one)
  - `simple_float` (float, one)
  - `simple_bool` (bool, one)
  - `simple_datetime` (datetime, one)
  - `simple_json` (json, one)
  - `simple_ref` (ref, one)

- Все типы с `cardinality = many`, названия: `arr_{data_type}`
  - `arr_string` (string, many)
  - `arr_text` (text, many)
  - `arr_int` (int, many)
  - `arr_float` (float, many)
  - `arr_bool` (bool, many)
  - `arr_datetime` (datetime, many)
  - `arr_json` (json, many)
  - `arr_ref` (ref, many)

#### Уровень 1: JSON объект `nested_object` (json, one)
- Все типы с `cardinality = one`: `simple_{data_type}`
  - `nested_object.simple_string`
  - `nested_object.simple_text`
  - `nested_object.simple_int`
  - `nested_object.simple_float`
  - `nested_object.simple_bool`
  - `nested_object.simple_datetime`
  - `nested_object.simple_json`
  - `nested_object.simple_ref`

- Все типы с `cardinality = many`: `arr_{data_type}`
  - `nested_object.arr_string`
  - `nested_object.arr_text`
  - `nested_object.arr_int`
  - `nested_object.arr_float`
  - `nested_object.arr_bool`
  - `nested_object.arr_datetime`
  - `nested_object.arr_json`
  - `nested_object.arr_ref`

- Вложенный JSON объект: `deep_object` (json, one)
  - `nested_object.deep_object.simple_string`
  - `nested_object.deep_object.simple_text`
  - `nested_object.deep_object.simple_int`
  - `nested_object.deep_object.simple_float`
  - `nested_object.deep_object.simple_bool`
  - `nested_object.deep_object.simple_datetime`
  - `nested_object.deep_object.simple_json`
  - `nested_object.deep_object.simple_ref`
  - `nested_object.deep_object.arr_string`
  - `nested_object.deep_object.arr_text`
  - `nested_object.deep_object.arr_int`
  - `nested_object.deep_object.arr_float`
  - `nested_object.deep_object.arr_bool`
  - `nested_object.deep_object.arr_datetime`
  - `nested_object.deep_object.arr_json`
  - `nested_object.deep_object.arr_ref`

- Вложенный JSON массив: `deep_array` (json, many)
  - `nested_object.deep_array.simple_string`
  - `nested_object.deep_array.simple_text`
  - `nested_object.deep_array.simple_int`
  - `nested_object.deep_array.simple_float`
  - `nested_object.deep_array.simple_bool`
  - `nested_object.deep_array.simple_datetime`
  - `nested_object.deep_array.simple_json`
  - `nested_object.deep_array.simple_ref`
  - `nested_object.deep_array.arr_string`
  - `nested_object.deep_array.arr_text`
  - `nested_object.deep_array.arr_int`
  - `nested_object.deep_array.arr_float`
  - `nested_object.deep_array.arr_bool`
  - `nested_object.deep_array.arr_datetime`
  - `nested_object.deep_array.arr_json`
  - `nested_object.deep_array.arr_ref`

**Итого:**
- Корень: 16 полей (8 simple + 8 arr)
- В nested_object: 16 полей (8 simple + 8 arr)
- В deep_object: 16 полей (8 simple + 8 arr)
- В deep_array: 16 полей (8 simple + 8 arr)
- **Всего: 64 поля**

---

## Этап 2: Дополнительные сложные кейсы

### 2.1 Blueprint: `validation_comprehensive`
**Цель:** Покрыть все типы валидации для всех типов данных

**Структура:**
- Для каждого типа данных создать поля с различными правилами валидации:
  - `required: true/false`
  - `min/max` для строк, чисел, массивов
  - `pattern` для строк
  - `enum` для строк
  - `nullable` для всех типов
  - Условные правила (conditional)

**Примеры:**
- `string_with_required_min_max`
- `int_with_range`
- `array_with_min_items`
- `string_with_pattern`
- `conditional_field` (валидируется только если другое поле = значение)

---

### 2.2 Blueprint: `indexing_comprehensive`
**Цель:** Покрыть все варианты индексации

**Структура:**
- Поля с `is_indexed = true` на всех уровнях вложенности
- Поля с `is_indexed = false` для сравнения
- Различные комбинации indexed/non-indexed в одной иерархии

**Примеры:**
- `indexed_root_field`
- `indexed_nested_field`
- `non_indexed_root_field`
- Комбинации в одной структуре

---

### 2.3 Blueprint: `embed_hierarchy`
**Цель:** Покрыть сложные случаи встраивания

**Структура:**
- Blueprint A встраивает Blueprint B
- Blueprint B встраивает Blueprint C
- Blueprint A встраивает Blueprint C напрямую
- Множественные встраивания в один host_path
- Встраивание в глубоко вложенный json объект (4+ уровня)

**Примеры:**
- `company` → встраивает `address` и `contacts`
- `address` → встраивает `geo` и `metadata`
- `article` → встраивает `company` → который встраивает `address`

---

### 2.4 Blueprint: `ref_types_comprehensive`
**Цель:** Покрыть все варианты ref типов

**Структура:**
- `ref` с `cardinality = one`
- `ref` с `cardinality = many`
- `ref` вложенный в json объекты
- `ref` в массивах json объектов
- `ref` в массивах массивов
- Циклические ссылки (статья ссылается на статью)

**Примеры:**
- `simple_ref` (one)
- `arr_ref` (many)
- `nested_object.ref_to_article`
- `arr_articles[].ref_to_author`
- `complex.ref_to_self`

---

### 2.5 Blueprint: `deep_nesting` (4+ уровня)
**Цель:** Протестировать максимальную глубину вложенности

**Структура:**
- level0 → level1 → level2 → level3 → level4 → level5
- На каждом уровне: простые поля + вложенный объект
- Проверить производительность и валидацию

**Пример:**
```
config.settings.ui.theme.colors.primary.hex
config.settings.ui.theme.colors.primary.rgb.r
config.settings.ui.theme.colors.primary.rgb.g
config.settings.ui.theme.colors.primary.rgb.b
```

---

### 2.6 Blueprint: `mixed_cardinality`
**Цель:** Сложные комбинации кардинальностей

**Структура:**
- Массив объектов, каждый объект содержит массив других объектов
- Объект с массивом объектов, каждый содержит массив примитивов
- Смешанные структуры

**Пример:**
```json
{
  "articles": [  // many
    {
      "title": "...",
      "tags": ["...", "..."],  // many внутри many
      "authors": [  // many
        {
          "name": "...",
          "contacts": ["...", "..."]  // many внутри many внутри many
        }
      ]
    }
  ]
}
```

---

### 2.7 Blueprint: `migration_scenarios`
**Цель:** Симуляция сценариев миграции схемы

**Структура:**
- Поля, которые предполагают будущее расширение
- Зарезервированные поля с префиксами (v1_, v2_)
- Депрекейтенные поля (marked as readonly)
- Поля с версионированием в названиях

**Примеры:**
- `v1_title` (deprecated, readonly)
- `v2_title` (current)
- `_reserved_field` (зарезервировано)

---

### 2.8 Blueprint: `edge_cases`
**Цель:** Граничные случаи и edge cases

**Структура:**
- Очень длинные названия полей
- Специальные символы в full_path (через name)
- Поля с одинаковыми именами на разных уровнях
- Пустые json объекты (только структура, без полей)
- Одинокий json массив без содержимого

**Примеры:**
- `very_long_field_name_that_exceeds_normal_expectations_123456789`
- `field` и `nested.field` (одинаковые имена)
- `empty_json_object` (json, one) без children

---

### 2.9 Blueprint: `real_world_examples`
**Цель:** Реалистичные сценарии использования

**Варианты:**
1. **E-commerce Product**
   - Базовая информация (название, описание, цена)
   - Вариации (размеры, цвета) - массив объектов
   - Медиа (галерея изображений, видео)
   - SEO (вложенный объект)
   - Отзывы (массив с вложенными пользователями)

2. **Blog Article**
   - Контент (title, body, excerpt)
   - Автор (вложенный объект или embed)
   - Категории и теги (массивы)
   - Метаданные (публикация, SEO, социальные сети)
   - Связанные статьи (ref many)
   - Комментарии (массив с вложенными объектами)

3. **User Profile**
   - Основная информация
   - Адреса (массив объектов)
   - Контакты (телефоны, emails - массивы)
   - Настройки (глубоко вложенный json)
   - История активности (массив объектов с временными метками)

---

## Этап 3: Создание Entry для тестирования

После создания всех Blueprint'ов создать Entry с реальными данными:
- Заполнить все поля валидными значениями
- Протестировать валидацию
- Протестировать индексацию (DocValue, DocRef)
- Протестировать поиск по индексированным полям

---

## Этап 4: Интеграционные тесты

Создать тесты, которые:
1. Проверяют создание всех blueprint'ов
2. Проверяют валидацию всех типов данных
3. Проверяют работу с глубокой вложенностью
4. Проверяют embed'ы
5. Проверяют индексацию
6. Проверяют производительность на больших структурах

---

## Порядок реализации

1. ✅ **Создать документ с планом** (текущий этап)
2. ⏳ **Этап 1**: Создать сидер `ComprehensiveTypesBlueprintSeeder` с основной структурой
3. ⏳ **Этап 2.1-2.9**: Создать дополнительные blueprint'ы
4. ⏳ **Этап 3**: Создать Entry с тестовыми данными
5. ⏳ **Этап 4**: Написать интеграционные тесты

---

## Технические детали

### Используемые типы данных:
- `string` - строка (короткая)
- `text` - текст (длинный)
- `int` - целое число
- `float` - число с плавающей точкой
- `bool` - boolean
- `datetime` - дата и время
- `json` - JSON объект/массив (для вложенности)
- `ref` - ссылка на Entry

### Кардинальности:
- `one` - одно значение
- `many` - массив значений

### Соглашения по именованию:
- `simple_{type}` - поле с cardinality=one
- `arr_{type}` - поле с cardinality=many
- `nested_object` - JSON объект первого уровня вложенности
- `deep_object` - JSON объект второго уровня
- `deep_array` - JSON массив внутри объекта

---

## Статистика покрытия

После реализации:

- **Типы данных**: 8/8 (100%)
- **Кардинальности**: 2/2 (100%)
- **Уровни вложенности**: 0-5+ уровней
- **Валидация**: все основные правила
- **Индексация**: все комбинации
- **Embed'ы**: множественные, вложенные, циклические
- **Ref типы**: все варианты использования

---

**Дата создания плана:** 2025-12-03
**Статус:** В процессе реализации

