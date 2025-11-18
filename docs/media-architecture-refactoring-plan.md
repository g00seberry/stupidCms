# План рефакторинга архитектуры медиафайлов

## Обзор

Документ описывает план миграции от монолитной структуры таблицы `media` к архитектуре Table Per Type (TPT) с разделением специфичных полей по типам медиа.

**Текущая проблема:**

-   Все типы медиа (image, video, audio, document) хранятся в одной таблице `media`
-   Поля `width`, `height`, `exif_json` применимы только к изображениям, но присутствуют у всех типов
-   Поле `duration_ms` дублируется в `media` и `media_metadata`
-   Множество nullable полей, которые не имеют смысла для определенных типов

**Целевая архитектура:**

-   `media` — общие поля для всех типов
-   `media_images` — специфичные поля для изображений (width, height, exif_json)
-   `media_av_metadata` — AV-метаданные для видео/аудио (duration_ms, bitrate, codecs, frame_rate)
-   Четкое разделение по типам без nullable полей для нерелевантных данных

---

## Последовательность задач (13 шагов)

### Этап 1: Подготовка инфраструктуры (задачи 1-4)

#### Задача 1: Создать миграцию для таблицы `media_images`

**Файл:** `database/migrations/YYYY_MM_DD_HHMMSS_create_media_images_table.php`

**Описание:**
Создать таблицу для хранения специфичных метаданных изображений.

**Структура таблицы:**

-   `id` (ULID, primary key)
-   `media_id` (ULID, unique, foreign key → media.id, cascade delete)
-   `width` (unsigned integer, NOT NULL)
-   `height` (unsigned integer, NOT NULL)
-   `exif_json` (JSON/JSONB, nullable)
-   `timestamps` (created_at, updated_at)

**Индексы:**

-   Уникальный индекс на `media_id`
-   Внешний ключ с `ON DELETE CASCADE`

**Критерии готовности:**

-   [ ] Миграция создана и проходит валидацию
-   [ ] Foreign key настроен корректно
-   [ ] Индексы созданы

---

#### Задача 2: Создать миграцию для переименования `media_metadata` → `media_av_metadata`

**Файл:** `database/migrations/YYYY_MM_DD_HHMMSS_rename_media_metadata_to_media_av_metadata.php`

**Описание:**
Переименовать таблицу `media_metadata` в `media_av_metadata` для ясности назначения (только AV-метаданные).

**Действия:**

1. Переименовать таблицу `media_metadata` → `media_av_metadata`
2. Обновить foreign key constraint (если требуется)
3. Обновить индексы (если требуется)

**Критерии готовности:**

-   [ ] Таблица переименована
-   [ ] Foreign key работает корректно
-   [ ] Все индексы сохранены

---

#### Задача 3: Создать миграцию для удаления полей из `media`

**Файл:** `database/migrations/YYYY_MM_DD_HHMMSS_remove_specific_fields_from_media_table.php`

**Описание:**
Удалить специфичные поля из таблицы `media`, которые будут перенесены в специализированные таблицы.

**Действия:**

1. Удалить колонку `width`
2. Удалить колонку `height`
3. Удалить колонку `duration_ms`
4. Удалить колонку `exif_json`
5. Удалить связанные индексы (если есть)

**Критерии готовности:**

-   [ ] Миграция создана
-   [ ] План отката подготовлен

---

#### Задача 4: Создать модель `MediaImage`

**Файл:** `app/Models/MediaImage.php`

**Описание:**
Создать Eloquent модель для таблицы `media_images` с типизированными свойствами и связями.

**Требования:**

-   Использовать `HasUlids` trait
-   Связь `belongsTo(Media::class)`
-   Cast для `exif_json` → `array`
-   Cast для `width`, `height` → `integer`
-   PHPDoc с полным описанием свойств
-   Методы доступа для вычисляемых полей (если нужны)

**Критерии готовности:**

-   [ ] Модель создана с полной документацией
-   [ ] Связи настроены корректно
-   [ ] Casts настроены
-   [ ] Соответствует PSR-12

---

### Этап 2: Обновление существующих моделей (задачи 5-6)

#### Задача 5: Переименовать модель `MediaMetadata` → `MediaAvMetadata`

**Файлы:**

-   `app/Models/MediaMetadata.php` → `app/Models/MediaAvMetadata.php`
-   Обновить все импорты в проекте

**Описание:**
Переименовать модель и обновить все места использования.

**Действия:**

1. Переименовать файл модели
2. Обновить namespace и имя класса
3. Обновить PHPDoc комментарии
4. Найти все импорты через `grep` и обновить
5. Обновить связи в модели `Media`

**Файлы для обновления:**

-   `app/Models/Media.php` (связь `metadata()`)
-   `app/Domain/Media/Actions/MediaStoreAction.php`
-   Все тесты, использующие `MediaMetadata`
-   Фабрики (`database/factories/MediaMetadataFactory.php`)

**Критерии готовности:**

-   [ ] Модель переименована
-   [ ] Все импорты обновлены
-   [ ] Связи работают корректно
-   [ ] Тесты проходят

---

#### Задача 6: Обновить модель `Media`: удалить поля и добавить связи

**Файл:** `app/Models/Media.php`

**Описание:**
Обновить модель `Media` для работы с новой архитектурой.

**Действия:**

1. Удалить свойства из PHPDoc: `width`, `height`, `duration_ms`, `exif_json`
2. Удалить casts для этих полей
3. Добавить связь `hasOne(MediaImage::class)` → `image()`
4. Обновить связь `metadata()` → `avMetadata()` (или оставить `metadata()` с новым типом)
5. Добавить методы-аксессоры для обратной совместимости (опционально):
    - `getWidthAttribute()` → `$this->image?->width`
    - `getHeightAttribute()` → `$this->image?->height`
    - `getExifJsonAttribute()` → `$this->image?->exif_json`
    - `getDurationMsAttribute()` → `$this->avMetadata?->duration_ms`

**Критерии готовности:**

-   [ ] PHPDoc обновлен
-   [ ] Casts удалены
-   [ ] Связи добавлены
-   [ ] Аксессоры работают (если добавлены)
-   [ ] Тесты обновлены

---

### Этап 3: Обновление сервисов и действий (задачи 7-9)

#### Задача 7: Обновить `MediaStoreAction` для создания связанных записей

**Файл:** `app/Domain/Media/Actions/MediaStoreAction.php`

**Описание:**
Обновить логику сохранения медиа для создания записей в специализированных таблицах.

**Изменения:**

1. После создания `Media`, определить тип через `kind()`
2. Для изображений (`kind() === 'image'`):
    - Создать запись в `media_images` с `width`, `height`, `exif_json`
    - Использовать данные из `MediaMetadataDTO`
    - Проверить, что `width` и `height` не null перед созданием
3. Для видео/аудио (`kind() === 'video'` или `'audio'`):
    - Создать/обновить запись в `media_av_metadata`
    - Использовать `duration_ms` из `MediaMetadataDTO` (не из `media`)
    - Сохранить все AV-метаданные (bitrate, codecs, frame_rate и т.д.)
    - Создавать запись только если есть хотя бы одно не-null поле
4. Убрать сохранение `width`, `height`, `duration_ms`, `exif_json` в `Media::create()`
5. Обновить импорт `MediaMetadata` → `MediaAvMetadata`
6. Добавить импорт `MediaImage`

**Критерии готовности:**

-   [ ] Логика создания связанных записей работает
-   [ ] Данные сохраняются корректно
-   [ ] Обработка ошибок добавлена
-   [ ] Импорты обновлены
-   [ ] Тесты обновлены и проходят

---

#### Задача 8: Обновить `MediaMetadataExtractor` и DTO

**Файлы:**

-   `app/Domain/Media/Services/MediaMetadataExtractor.php`
-   `app/Domain/Media/DTO/MediaMetadataDTO.php`

**Описание:**
Убедиться, что DTO и экстрактор возвращают корректные данные для новой структуры.

**Изменения:**

1. Проверить, что `MediaMetadataDTO` содержит все необходимые поля
2. Убедиться, что экстрактор корректно заполняет DTO
3. Обновить документацию методов

**Критерии готовности:**

-   [ ] DTO содержит все поля
-   [ ] Экстрактор работает корректно
-   [ ] Документация обновлена

---

#### Задача 9: Обновить `MediaResource` и контроллеры для загрузки связей

**Файлы:**

-   `app/Http/Resources/MediaResource.php`
-   `app/Http/Controllers/Admin/V1/MediaController.php`

**Описание:**
Обновить API Resource и контроллеры для работы с новой структурой данных.

**Изменения в MediaResource:**

1. В методе `toArray()` использовать связи вместо прямых полей:
    - `width` → `$this->image?->width`
    - `height` → `$this->image?->height`
    - `duration_ms` → `$this->avMetadata?->duration_ms`
2. Опционально: добавить вложенные объекты `image` и `av_metadata` в ответ API

**Изменения в MediaController:**

1. В методе `index()` добавить eager loading после получения пагинатора:
    ```php
    $paginator = $this->listAction->execute($mq)->appends($v);
    $paginator->getCollection()->load(['image', 'avMetadata']);
    ```
2. В методе `show()` добавить eager loading:
    ```php
    $media = Media::withTrashed()->with(['image', 'avMetadata'])->find($mediaId);
    ```
3. В методе `store()` добавить eager loading после создания:
    ```php
    $media = $this->storeAction->execute($file, $validated);
    $media->load(['image', 'avMetadata']);
    ```
4. В методе `update()` добавить eager loading после обновления:
    ```php
    $updated = $this->updateMetadataAction->execute($mediaId, $request->validated());
    $updated->load(['image', 'avMetadata']);
    ```
5. В методе `bulkRestore()` добавить eager loading:
    ```php
    foreach ($mediaItems as $media) {
        // ...
        $media->load(['image', 'avMetadata']);
        $restoredMedia[] = $media;
    }
    ```
6. Обновить примеры в PHPDoc комментариях (аннотации `@response`), убрав упоминания `width`, `height`, `duration_ms` из примеров или указав, что они берутся из связей

**Критерии готовности:**

-   [ ] Resource использует связи
-   [ ] Eager loading добавлен во всех методах контроллера
-   [ ] PHPDoc примеры обновлены
-   [ ] API ответы корректны
-   [ ] Тесты обновлены

---

### Этап 4: Обновление тестов и документации (задачи 10-12)

#### Задача 10: Обновить фабрики и тесты моделей

**Файлы:**

-   `database/factories/MediaFactory.php`
-   `database/factories/MediaImageFactory.php` (создать)
-   `database/factories/MediaAvMetadataFactory.php` (переименовать из MediaMetadataFactory)
-   Все тесты моделей
-   `tests/Helpers/Traits/CreatesMedia.php`

**Описание:**
Обновить фабрики и тесты для работы с новой архитектурой.

**Действия:**

1. Обновить `MediaFactory`:
    - Убрать `width`, `height`, `duration_ms`, `exif_json` из `definition()`
    - Обновить методы `image()`, `video()`, `audio()`, `document()`
    - Добавить методы для создания с связанными записями (например, `withImage()`)
2. Создать `MediaImageFactory`:
    - Связь с `Media` через `media_id`
    - Генерация `width`, `height`, `exif_json`
    - Метод `forMedia(Media $media)` для удобства
3. Переименовать `MediaMetadataFactory` → `MediaAvMetadataFactory`:
    - Обновить namespace и имя класса
    - Обновить связь с `Media`
4. Обновить все тесты:
    - `tests/Unit/Models/MediaTest.php` — убрать тесты на `width`, `height`, `duration_ms`, `exif_json` как прямые свойства
    - `tests/Feature/Models/MediaTest.php` — обновить тесты для работы со связями
    - Создать `tests/Unit/Models/MediaImageTest.php` — тесты для новой модели
    - Создать `tests/Feature/Models/MediaImageTest.php` — feature тесты
    - Обновить `tests/Unit/Models/MediaMetadataTest.php` → `MediaAvMetadataTest.php`
    - Обновить `tests/Feature/Models/MediaMetadataTest.php` → `MediaAvMetadataTest.php`
5. Обновить `tests/Helpers/Traits/CreatesMedia.php`:
    - Обновить метод `createMediaFile()` для поддержки создания с связанными записями

**Критерии готовности:**

-   [ ] Фабрики обновлены
-   [ ] Тесты обновлены и проходят
-   [ ] Покрытие тестами сохранено или улучшено
-   [ ] Хелперы для тестов обновлены

---

#### Задача 11: Обновить тесты сервисов, действий и API

**Файлы:**

-   `tests/Feature/Media/MediaStoreActionTest.php`
-   `tests/Unit/Domain/Media/Services/*Test.php`
-   `tests/Feature/Media/ListMediaActionTest.php`
-   `tests/Feature/Api/Media/*Test.php`
-   `tests/Unit/Domain/Media/DTO/MediaMetadataDTOTest.php`

**Описание:**
Обновить тесты сервисов, действий и API для работы с новой архитектурой.

**Действия:**

1. Обновить тесты `MediaStoreAction`:
    - Проверять создание записей в `media_images` для изображений
    - Проверять создание записей в `media_av_metadata` для видео/аудио
    - Проверять, что `width`, `height`, `duration_ms`, `exif_json` не сохраняются в `media`
    - Проверять корректность данных в связанных таблицах
2. Обновить тесты API:
    - `tests/Feature/Api/Media/ListMediaTest.php` — проверить, что ответы содержат данные из связей
    - `tests/Feature/Api/Media/ShowMediaTest.php` — проверить eager loading
    - `tests/Feature/Api/Media/UpdateMediaTest.php` — проверить, что обновление не затрагивает связанные таблицы
    - `tests/Feature/Api/Media/DeleteRestoreMediaTest.php` — проверить каскадное удаление
3. Обновить тесты других сервисов, использующих `Media`:
    - Проверить, что сервисы не используют прямые поля `width`, `height`, `duration_ms`, `exif_json`
    - Обновить моки и стабы для работы со связями
4. Обновить `MediaMetadataDTOTest`:
    - Убедиться, что DTO содержит все необходимые поля
    - Проверить методы `toArray()` и `fromArray()`

**Критерии готовности:**

-   [ ] Все тесты обновлены
-   [ ] Тесты проходят
-   [ ] Покрытие не снизилось
-   [ ] API тесты проверяют корректность ответов

---

#### Задача 12: Обновить документацию и PHPDoc

**Файлы:**

-   Все модели и сервисы
-   `docs/generated/README.md` (обновится автоматически)
-   API документация (Scribe)

**Описание:**
Обновить всю документацию для отражения новой архитектуры.

**Действия:**

1. Обновить PHPDoc во всех измененных классах
2. Проверить соответствие сигнатур методов и PHPDoc
3. Запустить `composer scribe:gen` для обновления API документации
4. Запустить `php artisan docs:generate` для обновления навигации

**Критерии готовности:**

-   [ ] PHPDoc обновлен везде
-   [ ] Сигнатуры соответствуют документации
-   [ ] API документация обновлена
-   [ ] Навигационная документация обновлена

---

### Этап 5: Финальная проверка (задача 13)

#### Задача 13: Выполнить финальную проверку и тестирование

**Описание:**
Выполнить финальную проверку всех изменений и убедиться, что система работает корректно.

**Действия:**

1. **Выполнение миграций:**

    - Выполнить миграции создания таблиц (задачи 1-2)
    - Выполнить миграцию удаления полей (задача 3)

2. **Проверка:**

    - Запустить все тесты: `php artisan test`
    - Проверить API endpoints вручную
    - Проверить работу загрузки медиа
    - Проверить работу просмотра медиа
    - Проверить работу вариантов изображений

3. **Мониторинг:**
    - Проверить логи на ошибки
    - Проверить производительность запросов
    - Убедиться, что eager loading работает корректно

**Критерии готовности:**

-   [ ] Миграции выполнены успешно
-   [ ] Все тесты проходят
-   [ ] API работает корректно
-   [ ] Нет ошибок в логах
-   [ ] Производительность не ухудшилась

---

## Чек-лист после выполнения всех задач

### Соответствие сигнатур

-   [ ] Количество параметров в методах = количество `@param` в PHPDoc
-   [ ] Типы параметров совпадают с типами в `@param`
-   [ ] Тип возвращаемого значения совпадает с `@return`

### Полнота документации

-   [ ] Все методы имеют PHPDoc
-   [ ] Все параметры конструктора документированы
-   [ ] Все свойства класса имеют `@var` аннотации
-   [ ] Все исключения указаны в `@throws`

### Актуальность описаний

-   [ ] Описание метода соответствует реальной логике
-   [ ] Описание параметров актуально
-   [ ] Указаны условия, при которых выбрасываются исключения

### Тестирование

-   [ ] Все тесты проходят: `php artisan test`
-   [ ] Покрытие тестами не снизилось
-   [ ] Добавлены тесты для новых моделей

### Документация

-   [ ] API документация обновлена: `composer scribe:gen`
-   [ ] Навигационная документация обновлена: `php artisan docs:generate`

---

## Команды для выполнения

После завершения всех задач выполнить:

```bash
# Запуск тестов
php artisan test

# Генерация API документации
composer scribe:gen

# Генерация навигационной документации
php artisan docs:generate
```

---

## Откат изменений

В случае необходимости отката:

1. Откатить миграции: `php artisan migrate:rollback`
2. Восстановить старые версии файлов из git

**Важно:** Все миграции должны быть обратимыми (метод `down()`).

---

## Примечания

-   Все миграции должны быть обратимыми (метод `down()`)
-   Eager loading связей критичен для производительности API — обязательно добавлять `with(['image', 'avMetadata'])` во всех местах, где загружается Media
-   Тесты должны покрывать все сценарии использования новой архитектуры
-   Так как система в активной разработке, миграция данных не требуется — новые записи будут создаваться сразу в новой структуре
-   **Важно:** `OnDemandVariantService` и `CorruptionValidator` используют `ImageProcessor` напрямую и не требуют изменений — они не зависят от полей в таблице `media`
-   **Важно:** `LogMediaEvent` логирует `width`/`height` только из `MediaVariant`, не из `Media` — не требует изменений
-   **Важно:** Примеры в PHPDoc комментариях контроллеров (`@response`) нужно обновить вручную, так как Scribe не обновит их автоматически

---

**Дата создания:** 2025-11-17  
**Версия:** 1.0  
**Статус:** План готов к реализации
