# Покрытие тестами системы валидации Blueprint

Документ описывает полный список тестовых сценариев для покрытия всех кейсов валидации данных в системе Blueprint.

## Структура документа

1. [Базовые типы данных](#базовые-типы-данных)
2. [Cardinality (one/many)](#cardinality-onemany)
3. [Required/Nullable](#requirednullable)
4. [Правила валидации](#правила-валидации)
5. [Вложенные поля](#вложенные-поля)
6. [Комбинации правил](#комбинации-правил)
7. [Граничные случаи](#граничные-случаи)
8. [Интеграционные тесты](#интеграционные-тесты)

---

## Базовые типы данных

### String/Text

**Тест 1.1**: Валидация строкового поля (required)
- Поле: `title` (string, required, cardinality: one)
- Успех: строка "Test Title"
- Ошибка: отсутствует поле
- Ошибка: null значение

**Тест 1.2**: Валидация текстового поля (nullable)
- Поле: `description` (text, nullable, cardinality: one)
- Успех: строка "Long description text"
- Успех: null значение
- Успех: отсутствует поле

**Тест 1.3**: Валидация строки с min/max
- Поле: `title` (string, required, min: 5, max: 100)
- Успех: строка длиной 50 символов
- Ошибка: строка длиной 3 символа (меньше min)
- Ошибка: строка длиной 150 символов (больше max)
- Ошибка: пустая строка (если min > 0)

**Тест 1.4**: Валидация строки с pattern
- Поле: `phone` (string, nullable, pattern: `^\\+?[1-9]\\d{1,14}$`)
- Успех: "+1234567890"
- Ошибка: "invalid-phone"
- Ошибка: "abc123"

### Integer

**Тест 2.1**: Валидация целого числа (required)
- Поле: `count` (int, required, cardinality: one)
- Успех: число 42
- Ошибка: отсутствует поле
- Ошибка: строка "42" (если строгая типизация)
- Ошибка: null значение

**Тест 2.2**: Валидация целого числа с min/max
- Поле: `age` (int, nullable, min: 0, max: 120)
- Успех: число 25
- Ошибка: число -5 (меньше min)
- Ошибка: число 150 (больше max)
- Успех: null значение

**Тест 2.3**: Валидация целого числа как ref
- Поле: `related_entry` (ref, nullable, exists: entries.id)
- Успех: существующий ID записи
- Ошибка: несуществующий ID
- Ошибка: строка вместо числа

### Float

**Тест 3.1**: Валидация числа с плавающей точкой
- Поле: `price` (float, nullable, cardinality: one)
- Успех: число 99.99
- Успех: число 100 (целое число)
- Ошибка: строка "99.99"

**Тест 3.2**: Валидация float с min/max
- Поле: `rating` (float, nullable, min: 0.0, max: 5.0)
- Успех: число 4.5
- Ошибка: число -0.5 (меньше min)
- Ошибка: число 6.0 (больше max)

### Boolean

**Тест 4.1**: Валидация булева значения
- Поле: `is_featured` (bool, nullable, cardinality: one)
- Успех: true
- Успех: false
- Успех: null значение
- Ошибка: строка "true"
- Ошибка: число 1

### Date/Datetime

**Тест 5.1**: Валидация даты
- Поле: `published_at` (date, nullable, cardinality: one)
- Успех: "2025-01-15"
- Успех: "2025-01-15T10:30:00Z"
- Ошибка: "invalid-date"
- Ошибка: "2025-13-45" (невалидная дата)

**Тест 5.2**: Валидация datetime
- Поле: `created_at` (datetime, nullable, cardinality: one)
- Успех: "2025-01-15T10:30:00Z"
- Успех: "2025-01-15 10:30:00"
- Ошибка: "2025-01-15" (только дата без времени)

### JSON (объект)

**Тест 6.1**: Валидация JSON объекта (cardinality: one)
- Поле: `author` (json, required, cardinality: one)
- Успех: объект `{"name": "John", "email": "john@example.com"}`
- Ошибка: отсутствует поле
- Ошибка: массив вместо объекта
- Ошибка: строка вместо объекта

**Тест 6.2**: Валидация JSON объекта (cardinality: many)
- Поле: `authors` (json, required, cardinality: many)
- Успех: массив объектов `[{"name": "John"}, {"name": "Jane"}]`
- Ошибка: отсутствует поле
- Ошибка: объект вместо массива
- Ошибка: массив строк вместо массива объектов
- Ошибка: массив с не-объектами

---

## Cardinality (one/many)

### Cardinality: one

**Тест 7.1**: Одиночное значение (string)
- Поле: `title` (string, required, cardinality: one)
- Успех: строка "Title"
- Ошибка: массив `["Title"]`
- Ошибка: объект `{"value": "Title"}`

**Тест 7.2**: Одиночное значение (int)
- Поле: `count` (int, required, cardinality: one)
- Успех: число 5
- Ошибка: массив `[5]`
- Ошибка: массив `[5, 10]`

### Cardinality: many

**Тест 8.1**: Массив строк
- Поле: `tags` (string, required, cardinality: many)
- Успех: массив `["tag1", "tag2", "tag3"]`
- Ошибка: отсутствует поле (если required)
- Ошибка: строка "tag1" вместо массива
- Ошибка: объект вместо массива

**Тест 8.2**: Массив чисел
- Поле: `scores` (int, nullable, cardinality: many)
- Успех: массив `[10, 20, 30]`
- Ошибка: массив `["10", "20"]` (строки вместо чисел)
- Ошибка: массив `[10, "20", 30]` (смешанные типы)

**Тест 8.3**: Массив объектов (json, cardinality: many)
- Поле: `authors` (json, required, cardinality: many)
- Успех: массив объектов `[{"name": "John"}, {"name": "Jane"}]`
- Ошибка: массив строк `["John", "Jane"]`
- Ошибка: объект `{"name": "John"}` вместо массива

**Тест 8.4**: Массив с array_min_items
- Поле: `tags` (string, required, cardinality: many, array_min_items: 2)
- Успех: массив `["tag1", "tag2"]`
- Ошибка: массив `["tag1"]` (меньше минимума)
- Ошибка: пустой массив `[]`

**Тест 8.5**: Массив с array_max_items
- Поле: `tags` (string, required, cardinality: many, array_max_items: 5)
- Успех: массив `["tag1", "tag2", "tag3", "tag4", "tag5"]`
- Ошибка: массив `["tag1", "tag2", "tag3", "tag4", "tag5", "tag6"]` (больше максимума)

**Тест 8.6**: Массив с array_min_items и array_max_items
- Поле: `tags` (string, required, cardinality: many, array_min_items: 2, array_max_items: 5)
- Успех: массив `["tag1", "tag2", "tag3"]`
- Ошибка: массив `["tag1"]` (меньше минимума)
- Ошибка: массив `["tag1", "tag2", "tag3", "tag4", "tag5", "tag6"]` (больше максимума)

**Тест 8.7**: Массив с array_unique
- Поле: `tags` (string, required, cardinality: many, array_unique: true)
- Успех: массив `["tag1", "tag2", "tag3"]`
- Ошибка: массив `["tag1", "tag2", "tag1"]` (дубликаты)

---

## Required/Nullable

**Тест 9.1**: Required поле
- Поле: `title` (string, required, cardinality: one)
- Ошибка: отсутствует поле
- Ошибка: null значение
- Успех: строка "Title"

**Тест 9.2**: Nullable поле
- Поле: `description` (text, nullable, cardinality: one)
- Успех: отсутствует поле
- Успех: null значение
- Успех: строка "Description"

**Тест 9.3**: Required массив
- Поле: `tags` (string, required, cardinality: many)
- Ошибка: отсутствует поле
- Ошибка: null значение
- Успех: массив `["tag1", "tag2"]`
- Успех: пустой массив `[]` (если array_min_items не задан)

**Тест 9.4**: Nullable массив
- Поле: `tags` (string, nullable, cardinality: many)
- Успех: отсутствует поле
- Успех: null значение
- Успех: массив `["tag1", "tag2"]`

---

## Правила валидации

### Min/Max

**Тест 10.1**: Min для строки
- Поле: `title` (string, required, min: 5)
- Успех: строка "Title" (5 символов)
- Ошибка: строка "Test" (4 символа)
- Ошибка: пустая строка ""

**Тест 10.2**: Max для строки
- Поле: `title` (string, required, max: 100)
- Успех: строка длиной 100 символов
- Ошибка: строка длиной 101 символ

**Тест 10.3**: Min/Max для целого числа
- Поле: `age` (int, nullable, min: 0, max: 120)
- Успех: число 50
- Ошибка: число -1 (меньше min)
- Ошибка: число 121 (больше max)

**Тест 10.4**: Min/Max для float
- Поле: `rating` (float, nullable, min: 0.0, max: 5.0)
- Успех: число 4.5
- Ошибка: число -0.1 (меньше min)
- Ошибка: число 5.1 (больше max)

**Тест 10.5**: Min/Max для элементов массива
- Поле: `tags` (string, required, cardinality: many, min: 2, max: 50)
- Успех: массив `["tag1", "tag2"]` (каждый элемент >= 2 символов)
- Ошибка: массив `["t", "tag2"]` (первый элемент < 2 символов)
- Ошибка: массив `[str_repeat("a", 51), "tag2"]` (первый элемент > 50 символов)

### Pattern

**Тест 11.1**: Pattern для строки
- Поле: `phone` (string, nullable, pattern: `^\\+?[1-9]\\d{1,14}$`)
- Успех: "+1234567890"
- Успех: "1234567890"
- Ошибка: "invalid-phone"
- Ошибка: "abc123"

**Тест 11.2**: Pattern для элементов массива
- Поле: `phones` (string, required, cardinality: many, pattern: `^\\+?[1-9]\\d{1,14}$`)
- Успех: массив `["+1234567890", "9876543210"]`
- Ошибка: массив `["+1234567890", "invalid"]`

### Conditional Rules

**Тест 12.1**: required_if (поле обязательно, если другое поле существует)
- Поле: `slug` (string, nullable, required_if: 'is_published')
- Успех: `is_published: false, slug: null`
- Ошибка: `is_published: true, slug: null`
- Успех: `is_published: true, slug: "test-slug"`

**Тест 12.2**: required_if с значением
- Поле: `slug` (string, nullable, required_if: ['field' => 'is_published', 'value' => true])
- Успех: `is_published: false, slug: null`
- Ошибка: `is_published: true, slug: null`
- Успех: `is_published: true, slug: "test-slug"`

**Тест 12.3**: required_if с оператором
- Поле: `draft_note` (text, nullable, required_if: ['field' => 'is_published', 'value' => false, 'operator' => '=='])
- Успех: `is_published: true, draft_note: null`
- Ошибка: `is_published: false, draft_note: null`
- Успех: `is_published: false, draft_note: "Draft note"`

**Тест 12.4**: prohibited_unless
- Поле: `draft_note` (text, nullable, prohibited_unless: ['field' => 'is_published', 'value' => false])
- Успех: `is_published: false, draft_note: "Note"`
- Ошибка: `is_published: true, draft_note: "Note"`
- Успех: `is_published: true, draft_note: null`

**Тест 12.5**: required_unless
- Поле: `slug` (string, nullable, required_unless: ['field' => 'is_published', 'value' => false])
- Успех: `is_published: false, slug: null`
- Ошибка: `is_published: true, slug: null`
- Успех: `is_published: true, slug: "test-slug"`

**Тест 12.6**: prohibited_if
- Поле: `draft_note` (text, nullable, prohibited_if: ['field' => 'is_published', 'value' => true])
- Успех: `is_published: false, draft_note: "Note"`
- Ошибка: `is_published: true, draft_note: "Note"`
- Успех: `is_published: true, draft_note: null`

### Unique

**Тест 13.1**: Unique в таблице
- Поле: `email` (string, required, unique: 'users')
- Успех: уникальный email
- Ошибка: email уже существует в таблице users

**Тест 13.2**: Unique с указанием колонки
- Поле: `email` (string, required, unique: ['table' => 'users', 'column' => 'email'])
- Успех: уникальный email
- Ошибка: email уже существует

**Тест 13.3**: Unique с except (для update)
- Поле: `email` (string, required, unique: ['table' => 'users', 'column' => 'email', 'except' => ['column' => 'id', 'value' => 1]])
- Успех: email уникален или принадлежит записи с id=1
- Ошибка: email существует у другой записи

**Тест 13.4**: Unique с where условием
- Поле: `slug` (string, required, unique: ['table' => 'entries', 'column' => 'slug', 'where' => ['column' => 'post_type_id', 'value' => 1]])
- Успех: slug уникален в рамках post_type_id=1
- Ошибка: slug уже существует для post_type_id=1

### Exists

**Тест 14.1**: Exists в таблице
- Поле: `related_entry` (ref, nullable, exists: 'entries')
- Успех: существующий ID записи
- Ошибка: несуществующий ID

**Тест 14.2**: Exists с указанием колонки
- Поле: `related_entry` (ref, nullable, exists: ['table' => 'entries', 'column' => 'id'])
- Успех: существующий ID
- Ошибка: несуществующий ID

**Тест 14.3**: Exists с where условием
- Поле: `related_entry` (ref, nullable, exists: ['table' => 'entries', 'column' => 'id', 'where' => ['column' => 'status', 'value' => 'published']])
- Успех: ID опубликованной записи
- Ошибка: ID неопубликованной записи

### Field Comparison

**Тест 15.1**: field_comparison (>=)
- Поле: `end_date` (date, nullable, field_comparison: ['operator' => '>=', 'field' => 'content_json.start_date'])
- Успех: `start_date: "2025-01-01", end_date: "2025-01-15"`
- Ошибка: `start_date: "2025-01-15", end_date: "2025-01-01"`

**Тест 15.2**: field_comparison с константой
- Поле: `published_at` (datetime, nullable, field_comparison: ['operator' => '>=', 'value' => '2025-01-01'])
- Успех: `published_at: "2025-01-15"`
- Ошибка: `published_at: "2024-12-31"`

**Тест 15.3**: field_comparison с разными операторами
- Поле: `end_date` (date, nullable, field_comparison: ['operator' => '>', 'field' => 'content_json.start_date'])
- Успех: `start_date: "2025-01-01", end_date: "2025-01-02"`
- Ошибка: `start_date: "2025-01-01", end_date: "2025-01-01"` (равны, но нужен >)

---

## Вложенные поля

**Тест 16.1**: Простое вложенное поле
- Поле: `author.name` (string, required, cardinality: one)
- Успех: `author: {name: "John"}`
- Ошибка: `author: {}` (отсутствует name)
- Ошибка: `author: null` (если author required)

**Тест 16.2**: Многоуровневая вложенность
- Поле: `author.contacts.phone` (string, nullable, cardinality: one)
- Успех: `author: {contacts: {phone: "+1234567890"}}`
- Ошибка: `author: {contacts: {}}` (если phone required)
- Успех: `author: {contacts: null}` (если contacts nullable)

**Тест 16.3**: Вложенное поле с правилами
- Поле: `author.name` (string, required, min: 2, max: 100, cardinality: one)
- Успех: `author: {name: "John Doe"}`
- Ошибка: `author: {name: "J"}` (меньше min)
- Ошибка: `author: {name: str_repeat("a", 101)}` (больше max)

**Тест 16.4**: Вложенное поле внутри массива объектов
- Поле: `author` (json, required, cardinality: many)
- Поле: `author.name` (string, required, cardinality: one)
- Успех: `author: [{name: "John"}, {name: "Jane"}]`
- Ошибка: `author: [{name: "John"}, {}]` (отсутствует name во втором объекте)
- Ошибка: `author: [{name: ["John"]}]` (name должен быть строкой, а не массивом)

**Тест 16.5**: Многоуровневая вложенность внутри массива
- Поле: `articles` (json, required, cardinality: many)
- Поле: `articles.author.name` (string, required, cardinality: one)
- Успех: `articles: [{author: {name: "John"}}, {author: {name: "Jane"}}]`
- Ошибка: `articles: [{author: {name: "John"}}, {author: {}}]`

**Тест 16.6**: Массив внутри объекта внутри массива
- Поле: `articles` (json, required, cardinality: many)
- Поле: `articles.tags` (string, required, cardinality: many)
- Успех: `articles: [{tags: ["tag1", "tag2"]}, {tags: ["tag3"]}]`
- Ошибка: `articles: [{tags: ["tag1"]}, {tags: "tag2"}]` (tags должен быть массивом)

---

## Комбинации правил

**Тест 17.1**: Required + Min + Max
- Поле: `title` (string, required, min: 5, max: 100)
- Успех: строка длиной 50 символов
- Ошибка: отсутствует поле
- Ошибка: строка длиной 3 символа
- Ошибка: строка длиной 150 символов

**Тест 17.2**: Nullable + Pattern + Min/Max
- Поле: `phone` (string, nullable, pattern: `^\\+?[1-9]\\d{1,14}$`, min: 10, max: 15)
- Успех: "+1234567890"
- Успех: null
- Ошибка: "123" (не соответствует pattern и меньше min)

**Тест 17.3**: Cardinality many + array_min_items + array_max_items + min/max для элементов
- Поле: `tags` (string, required, cardinality: many, array_min_items: 2, array_max_items: 5, min: 2, max: 50)
- Успех: массив `["tag1", "tag2", "tag3"]` (каждый элемент >= 2 и <= 50 символов)
- Ошибка: массив `["tag1"]` (меньше array_min_items)
- Ошибка: массив `["tag1", "tag2", "tag3", "tag4", "tag5", "tag6"]` (больше array_max_items)
- Ошибка: массив `["t", "tag2"]` (первый элемент меньше min)

**Тест 17.4**: Required_if + Min/Max
- Поле: `slug` (string, nullable, required_if: 'is_published', min: 1, max: 255)
- Успех: `is_published: false, slug: null`
- Ошибка: `is_published: true, slug: null`
- Ошибка: `is_published: true, slug: ""` (меньше min)
- Успех: `is_published: true, slug: "test-slug"`

**Тест 17.5**: Unique + Exists + Min/Max
- Поле: `email` (string, required, unique: 'users', min: 5, max: 255)
- Успех: уникальный email длиной 20 символов
- Ошибка: email уже существует
- Ошибка: email длиной 3 символа (меньше min)

**Тест 17.6**: Field comparison + Required_if
- Поле: `end_date` (date, nullable, field_comparison: ['operator' => '>=', 'field' => 'content_json.start_date'], required_if: 'is_published')
- Успех: `is_published: false, start_date: null, end_date: null`
- Ошибка: `is_published: true, start_date: "2025-01-15", end_date: null`
- Ошибка: `is_published: true, start_date: "2025-01-15", end_date: "2025-01-10"` (end_date < start_date)
- Успех: `is_published: true, start_date: "2025-01-10", end_date: "2025-01-15"`

---

## Граничные случаи

**Тест 18.1**: Пустой массив для required поля
- Поле: `tags` (string, required, cardinality: many)
- Успех: пустой массив `[]` (если array_min_items не задан)
- Ошибка: пустой массив `[]` (если array_min_items > 0)

**Тест 18.2**: Пустая строка для required поля
- Поле: `title` (string, required, min: 1)
- Ошибка: пустая строка ""
- Ошибка: строка из пробелов "   " (если trim применяется)

**Тест 18.3**: Null для required поля
- Поле: `title` (string, required)
- Ошибка: null значение
- Ошибка: отсутствует поле

**Тест 18.4**: Min равен Max
- Поле: `code` (string, required, min: 5, max: 5)
- Успех: строка длиной 5 символов
- Ошибка: строка длиной 4 символа
- Ошибка: строка длиной 6 символов

**Тест 18.5**: Min больше Max (невалидная конфигурация)
- Поле: `code` (string, required, min: 10, max: 5)
- Поведение: правила min/max игнорируются (логируется ошибка)

**Тест 18.6**: Очень длинная строка
- Поле: `content` (text, nullable, max: 10000)
- Успех: строка длиной 10000 символов
- Ошибка: строка длиной 10001 символ

**Тест 18.7**: Очень большой массив
- Поле: `items` (string, required, cardinality: many, array_max_items: 1000)
- Успех: массив из 1000 элементов
- Ошибка: массив из 1001 элемента

**Тест 18.8**: Смешанные типы в массиве
- Поле: `tags` (string, required, cardinality: many)
- Ошибка: массив `["tag1", 123, true]` (смешанные типы)
- Ошибка: массив `["tag1", {"key": "value"}]` (объект в массиве строк)

**Тест 18.9**: Вложенность на максимальной глубине
- Поле: `level1.level2.level3.level4.level5.value` (string, required)
- Успех: корректная структура с 5 уровнями вложенности
- Ошибка: отсутствует поле на любом уровне

**Тест 18.10**: Специальные символы в строках
- Поле: `title` (string, required, pattern: `^[a-zA-Z0-9\\s]+$`)
- Успех: "Title 123"
- Ошибка: "Title@#$" (специальные символы)

---

## Интеграционные тесты

**Тест 19.1**: Создание Entry с полным набором правил
- Blueprint с множеством полей разных типов
- Все правила валидации применены
- Успех: все поля валидны
- Ошибка: одно или несколько полей невалидны

**Тест 19.2**: Обновление Entry с частичными данными
- Существующая Entry с заполненными полями
- Обновление только части полей
- Успех: обновлённые поля валидны, остальные остаются без изменений
- Ошибка: обновлённые поля невалидны

**Тест 19.3**: Валидация с условными правилами на уровне Entry
- Поле `is_published` влияет на обязательность других полей
- Успех: все условные правила соблюдены
- Ошибка: нарушены условные правила

**Тест 19.4**: Валидация с зависимостями между полями
- Поле `end_date` должно быть >= `start_date`
- Поле `max_price` должно быть >= `min_price`
- Успех: зависимости соблюдены
- Ошибка: зависимости нарушены

**Тест 19.5**: Валидация с уникальностью в контексте Entry
- Поле `slug` должно быть уникальным в рамках PostType
- Успех: уникальный slug
- Ошибка: slug уже существует для другого Entry того же PostType

**Тест 19.6**: Валидация с exists в контексте Entry
- Поле `related_entry` должно ссылаться на существующую Entry
- Успех: существующая Entry
- Ошибка: несуществующая Entry

**Тест 19.7**: Валидация сложной структуры данных
- Blueprint с глубокой вложенностью (5+ уровней)
- Массивы объектов с вложенными массивами
- Успех: вся структура валидна
- Ошибка: ошибка на любом уровне вложенности

**Тест 19.8**: Производительность валидации
- Blueprint с 100+ полями
- Entry с заполненными всеми полями
- Валидация должна завершиться < 1 секунды

**Тест 19.9**: Кэширование правил валидации
- Правила валидации кэшируются для Blueprint
- Изменение структуры Blueprint инвалидирует кэш
- Успех: кэш работает корректно

**Тест 19.10**: Валидация при отсутствии Blueprint
- Entry без привязанного Blueprint
- Успех: валидация проходит (нет правил для content_json)

---

## Чек-лист покрытия

### Типы данных
- [ ] string
- [ ] text
- [ ] int
- [ ] float
- [ ] bool
- [ ] date
- [ ] datetime
- [ ] json (cardinality: one)
- [ ] json (cardinality: many)
- [ ] ref

### Cardinality
- [ ] one (одиночное значение)
- [ ] many (массив значений)
- [ ] many с json (массив объектов)

### Required/Nullable
- [ ] required поле
- [ ] nullable поле
- [ ] required массив
- [ ] nullable массив

### Правила валидации
- [ ] min (для строк, чисел)
- [ ] max (для строк, чисел)
- [ ] pattern
- [ ] array_min_items
- [ ] array_max_items
- [ ] array_unique
- [ ] required_if
- [ ] prohibited_unless
- [ ] required_unless
- [ ] prohibited_if
- [ ] unique (простой формат)
- [ ] unique (с параметрами)
- [ ] exists (простой формат)
- [ ] exists (с параметрами)
- [ ] field_comparison (с полем)
- [ ] field_comparison (с константой)

### Вложенность
- [ ] Простое вложенное поле (1 уровень)
- [ ] Многоуровневая вложенность (2+ уровня)
- [ ] Вложенное поле внутри массива объектов
- [ ] Массив внутри объекта внутри массива
- [ ] Глубокая вложенность (5+ уровней)

### Комбинации
- [ ] Required + Min/Max
- [ ] Nullable + Pattern + Min/Max
- [ ] Cardinality many + array_min_items + array_max_items + min/max
- [ ] Required_if + Min/Max
- [ ] Unique + Exists + Min/Max
- [ ] Field comparison + Required_if

### Граничные случаи
- [ ] Пустой массив
- [ ] Пустая строка
- [ ] Null значение
- [ ] Min = Max
- [ ] Min > Max (невалидная конфигурация)
- [ ] Очень длинные строки
- [ ] Очень большие массивы
- [ ] Смешанные типы
- [ ] Специальные символы

### Интеграция
- [ ] Создание Entry
- [ ] Обновление Entry
- [ ] Условные правила
- [ ] Зависимости между полями
- [ ] Уникальность в контексте
- [ ] Exists в контексте
- [ ] Сложные структуры
- [ ] Производительность
- [ ] Кэширование
- [ ] Отсутствие Blueprint

---

## Приоритеты тестирования

### Критичные (P0)
- Базовые типы данных (string, int, float, bool)
- Required/Nullable
- Cardinality (one/many)
- Min/Max для строк и чисел
- Простые вложенные поля

### Важные (P1)
- Pattern
- Array rules (array_min_items, array_max_items, array_unique)
- Conditional rules (required_if, prohibited_unless)
- Вложенные поля внутри массивов
- Unique и Exists

### Желательные (P2)
- Field comparison
- Глубокая вложенность
- Комбинации правил
- Граничные случаи
- Производительность

---

## Примечания

1. Все тесты должны проверять как успешные, так и неуспешные сценарии
2. Тесты должны покрывать как создание (POST), так и обновление (PUT) Entry
3. При тестировании условных правил нужно проверять все возможные комбинации условий
4. Тесты производительности должны выполняться отдельно от основных тестов
5. При изменении структуры валидации необходимо обновить этот документ

