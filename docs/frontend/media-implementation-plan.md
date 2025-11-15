# План реализации функционала работы с медиафайлами на фронтенде

**Проект:** StupidCMS Headless CMS  
**Дата создания:** 2025-01-14  
**Версия:** 1.2  
**Последнее обновление:** 2025-01-15

## Обзор

План из 20 задач для реализации полного функционала управления медиафайлами на фронтенде. Основан на API endpoints, описанных в `app/Http/Controllers/Admin/V1/MediaController.php`.

### API Endpoints

-   `GET /api/v1/admin/media` - список с фильтрами и пагинацией
-   `POST /api/v1/admin/media` - загрузка файла
-   `GET /api/v1/admin/media/{id}` - детали медиа
-   `PUT /api/v1/admin/media/{id}` - обновление метаданных
-   `DELETE /api/v1/admin/media/{id}` - удаление (soft delete)
-   `POST /api/v1/admin/media/{id}/restore` - восстановление
-   `GET /api/v1/admin/media/{id}/preview?variant={variant}` - превью
-   `GET /api/v1/admin/media/{id}/download` - скачивание

### Типы медиа

-   `image` - изображения (JPEG, PNG, GIF, WebP)
-   `video` - видео (MP4)
-   `audio` - аудио (MP3)
-   `document` - документы (PDF)

**Примечание:** Поддерживаемые MIME-типы определяются конфигурацией `config/media.php`:

-   `image/jpeg`, `image/png`, `image/webp`, `image/gif`
-   `video/mp4`
-   `audio/mpeg`
-   `application/pdf`

Максимальный размер загрузки: 25 МБ (по умолчанию, настраивается через `MEDIA_MAX_UPLOAD_MB`).

---

## Задачи

### Блок 1: Базовая инфраструктура (1-5)

#### Задача 1: Создание TypeScript типов для Media API

**Приоритет:** Высокий  
**Сложность:** Низкая  
**Зависимости:** Нет

**Описание:**
Создать типы TypeScript для всех сущностей Media API:

-   `Media` - основной тип медиафайла
-   `MediaKind` - тип медиа: `'image' | 'video' | 'audio' | 'document'`
-   `MediaCollection` - ответ списка с пагинацией
-   `MediaFilters` - параметры фильтрации
-   `MediaSortField` - поля сортировки
-   `MediaPreviewUrls` - URLs превью для вариантов

**Файлы:**

-   `src/types/media.ts`

**Детали:**

```typescript
interface Media {
    id: string;
    kind: MediaKind;
    name: string; // original_name из бэкенда
    ext: string;
    mime: string;
    size_bytes: number;
    width: number | null;
    height: number | null;
    duration_ms: number | null;
    title: string | null;
    alt: string | null;
    collection: string | null;
    created_at: string; // ISO 8601
    updated_at: string; // ISO 8601
    deleted_at: string | null; // ISO 8601 или null
    preview_urls: Record<string, string>; // Только для изображений, может быть пустым объектом
    download_url: string;
}
```

**Примечания:**

-   `preview_urls` содержит URLs для вариантов превью (только для изображений). Варианты: `thumbnail`, `medium` (настраиваются в `config/media.php`).
-   `preview_urls` может быть пустым объектом `{}` для не-изображений.
-   Preview endpoint возвращает 302 редирект на подписанный URL хранилища.
-   Поиск (`q` параметр) ищет по полям `title` и `original_name` (LIKE запрос).

**Критерии приёмки:**

-   Все типы покрывают структуру ответов API
-   Типы строго типизированы (без `any`)
-   Экспорт всех типов для использования в других модулях

---

#### Задача 2: Создание API клиента для Media

**Приоритет:** Высокий  
**Сложность:** Средняя  
**Зависимости:** Задача 1

**Описание:**
Реализовать клиент для работы с Media API:

-   Методы для всех endpoints
-   Обработка ошибок (401, 404, 409, 422, 429)
-   Типизация запросов и ответов
-   Поддержка загрузки файлов (FormData)

**Файлы:**

-   `src/api/media.ts` или `src/services/media.service.ts`

**Методы:**

-   `listMedia(filters?: MediaFilters): Promise<MediaCollection>`
-   `getMedia(id: string): Promise<Media>`
-   `uploadMedia(file: File, metadata?: { title?: string; alt?: string; collection?: string }): Promise<Media>`
-   `updateMedia(id: string, metadata: { title?: string; alt?: string; collection?: string }): Promise<Media>`
-   `deleteMedia(id: string): Promise<void>`
-   `restoreMedia(id: string): Promise<Media>`
-   `getMediaPreviewUrl(id: string, variant: string): string` // Возвращает URL endpoint (302 редирект)
-   `getMediaDownloadUrl(id: string): string` // Возвращает URL endpoint для скачивания

**Критерии приёмки:**

-   Все методы покрывают API endpoints
-   Корректная обработка ошибок с типизацией (401, 404, 409, 422, 429, 500)
-   Поддержка пагинации в `listMedia` (default: `per_page=15`, диапазон: 1-100)
-   Валидация входных параметров
-   Обработка 409 ошибки при удалении (Media in use) с информацией о связанных записях
-   Preview endpoint возвращает 302 редирект (обрабатывать как redirect)

---

#### Задача 3: Создание утилит для работы с медиа

**Приоритет:** Средний  
**Сложность:** Низкая  
**Зависимости:** Задача 1

**Описание:**
Утилиты для форматирования и работы с медиафайлами:

-   Форматирование размера файла (bytes → KB/MB/GB)
-   Форматирование длительности (ms → mm:ss)
-   Определение иконки по типу/расширению
-   Валидация файлов перед загрузкой
-   Генерация превью URL

**Файлы:**

-   `src/utils/media.ts`

**Функции:**

-   `formatFileSize(bytes: number): string`
-   `formatDuration(ms: number | null): string | null`
-   `getMediaIcon(kind: MediaKind, mime?: string): string`
-   `validateMediaFile(file: File): { valid: boolean; error?: string }` // Проверка MIME типа и размера (max 25MB)
-   `getPreviewUrl(media: Media, variant?: string): string | null` // Возвращает URL из preview_urls или null
-   `getAllowedMimeTypes(): string[]` // Возвращает список разрешенных MIME типов

**Критерии приёмки:**

-   Корректное форматирование для всех единиц измерения
-   Поддержка всех типов медиа (image, video, audio, document)
-   Валидация MIME-типов на основе конфигурации бэкенда (`config/media.php`)
-   Валидация максимального размера файла (25 МБ по умолчанию)
-   Поддержка только разрешенных MIME типов: `image/jpeg`, `image/png`, `image/webp`, `image/gif`, `video/mp4`, `audio/mpeg`, `application/pdf`

---

#### Задача 4: Создание компонента MediaCard

**Приоритет:** Высокий  
**Сложность:** Средняя  
**Зависимости:** Задачи 1, 3

**Описание:**
Компонент карточки медиафайла для отображения в списке:

-   Превью (изображение/иконка)
-   Название и метаданные
-   Индикатор типа
-   Действия (редактировать, удалить, скачать)
-   Поддержка выбора (checkbox)

**Файлы:**

-   `src/components/media/MediaCard.tsx`

**Пропсы:**

-   `media: Media` - данные медиа
-   `selectable?: boolean` - возможность выбора
-   `selected?: boolean` - состояние выбора
-   `showActions?: boolean` - показывать действия

**Пропсы для обработчиков:**

-   `onClick?: (media: Media) => void` - клик по карточке
-   `onSelect?: (media: Media, selected: boolean) => void` - выбор/снятие выбора
-   `onEdit?: (media: Media) => void` - редактирование
-   `onDelete?: (media: Media) => void` - удаление
-   `onDownload?: (media: Media) => void` - скачивание

**Критерии приёмки:**

-   Адаптивный дизайн (responsive)
-   Корректное отображение всех типов медиа
-   Лоадер для превью
-   Обработка ошибок загрузки изображений

---

#### Задача 5: Создание MobX Store для Media

**Приоритет:** Высокий  
**Сложность:** Средняя  
**Зависимости:** Задачи 1, 2

**Описание:**
MobX store для управления состоянием медиа:

-   Загрузка списка с фильтрами
-   Загрузка деталей
-   CRUD операции
-   Управление пагинацией
-   Обработка состояний (loading, error)

**Файлы:**

-   `src/stores/mediaStore.ts`

**Структура store:**

```typescript
import { makeAutoObservable, runInAction } from "mobx";
import { Media, MediaFilters, MediaCollection } from "@/types/media";
import { mediaApi } from "@/api/media";

class MediaStore {
    mediaList: Media[] = [];
    currentMedia: Media | null = null;
    loading = false;
    error: string | null = null;
    filters: MediaFilters = {};
    pagination = {
        currentPage: 1,
        perPage: 15, // Default из бэкенда
        total: 0,
        lastPage: 1,
    };

    constructor() {
        makeAutoObservable(this);
    }

    async loadMediaList(): Promise<void> {
        this.loading = true;
        this.error = null;
        try {
            const response = await mediaApi.listMedia(this.filters);
            runInAction(() => {
                this.mediaList = response.data;
                this.pagination = {
                    currentPage: response.meta.current_page,
                    perPage: response.meta.per_page,
                    total: response.meta.total,
                    lastPage: response.meta.last_page,
                };
                this.loading = false;
            });
        } catch (error) {
            runInAction(() => {
                this.error = error.message;
                this.loading = false;
            });
        }
    }

    async loadMedia(id: string): Promise<void> {
        // ... загрузка деталей
    }

    async uploadMedia(file: File, metadata?: Partial<Media>): Promise<Media> {
        // ... загрузка файла
    }

    async updateMedia(id: string, metadata: Partial<Media>): Promise<void> {
        // ... обновление
    }

    async deleteMedia(id: string): Promise<void> {
        // ... удаление
    }

    async restoreMedia(id: string): Promise<void> {
        // ... восстановление
    }

    setFilters(filters: Partial<MediaFilters>): void {
        this.filters = { ...this.filters, ...filters };
    }

    setPage(page: number): void {
        this.pagination.currentPage = page;
    }

    reset(): void {
        this.mediaList = [];
        this.currentMedia = null;
        this.filters = {};
        this.error = null;
    }
}

export const mediaStore = new MediaStore();
```

**Использование в компонентах:**

```typescript
import { observer } from "mobx-react-lite";
import { useEffect } from "react";
import { mediaStore } from "@/stores/mediaStore";

// В компоненте:
const MediaList = observer(() => {
    const { mediaList, loading, error, loadMediaList } = mediaStore;

    useEffect(() => {
        loadMediaList();
    }, [loadMediaList]);

    if (loading) return <div>Loading...</div>;
    if (error) return <div>Error: {error}</div>;

    return (
        <div>
            {mediaList.map((media) => (
                <MediaCard key={media.id} media={media} />
            ))}
        </div>
    );
});
```

**Критерии приёмки:**

-   Реактивность через MobX observable
-   Все действия помечены как @action или через makeAutoObservable
-   Вычисляемые свойства через @computed (если нужны)
-   Обработка всех состояний загрузки
-   Валидация данных перед отправкой

---

### Блок 2: Компоненты отображения (6-10)

#### Задача 6: Создание компонента MediaList

**Приоритет:** Высокий  
**Сложность:** Средняя  
**Зависимости:** Задачи 4, 5

**Примечание:** Компонент использует `mediaStore` для получения данных и управления состоянием.

**Описание:**
Компонент списка медиафайлов:

-   Сетка/список карточек (переключение режимов)
-   Пагинация
-   Индикатор загрузки
-   Пустое состояние
-   Поддержка множественного выбора

**Файлы:**

-   `src/components/media/MediaList.tsx`

**Пропсы:**

-   `viewMode?: 'grid' | 'list'` - режим отображения
-   `selectable?: boolean` - множественный выбор
-   `filters?: MediaFilters` - начальные фильтры

**Пропсы для обработчиков:**

-   `onSelect?: (media: Media) => void` - выбор медиа
-   `onSelectMultiple?: (media: Media[]) => void` - выбор нескольких

**Критерии приёмки:**

-   Виртуализация для больших списков (опционально)
-   Infinite scroll или пагинация
-   Плавная анимация загрузки
-   Адаптивная сетка

---

#### Задача 7: Создание компонента MediaPreview

**Приоритет:** Высокий  
**Сложность:** Средняя  
**Зависимости:** Задачи 1, 3

**Описание:**
Компонент превью медиафайла:

-   Изображения: с вариантами превью (thumbnail, medium) из `preview_urls`
-   Видео: видеоплеер с controls (MP4)
-   Аудио: аудиоплеер с controls (MP3)
-   Документы: иконка + метаданные (PDF)
-   Zoom для изображений
-   Обработка 302 редиректа для preview URLs (если используется напрямую)

**Файлы:**

-   `src/components/media/MediaPreview.tsx`

**Пропсы:**

-   `media: Media` - данные медиа
-   `variant?: string` - вариант превью (для изображений: `thumbnail`, `medium`)
-   `zoomable?: boolean` - возможность увеличения

**Критерии приёмки:**

-   Оптимизированная загрузка изображений (lazy)
-   Fallback для неудачной загрузки
-   Адаптивный видеоплеер
-   Доступность (accessibility)

---

#### Задача 8: Создание компонента MediaDetails

**Приоритет:** Высокий  
**Сложность:** Средняя  
**Зависимости:** Задачи 1, 2, 7

**Описание:**
Компонент детального просмотра медиафайла:

-   Полное превью
-   Все метаданные (размер, размеры, дата создания и т.д.)
-   Список связанных записей (Entry)
-   История изменений (опционально)
-   Действия (редактировать, удалить, восстановить, скачать)

**Файлы:**

-   `src/components/media/MediaDetails.tsx` или `src/pages/media/MediaDetails.tsx`

**Пропсы:**

-   `mediaId: string` - ID медиа

**Критерии приёмки:**

-   Загрузка данных через API
-   Обработка ошибок (404)
-   Редактирование метаданных (inline или модальное окно)
-   Навигация назад

---

#### Задача 9: Создание компонента MediaUploader

**Приоритет:** Высокий  
**Сложность:** Высокая  
**Зависимости:** Задачи 1, 2, 3

**Описание:**
Компонент загрузки медиафайлов:

-   Drag & drop
-   Множественная загрузка
-   Прогресс-бар для каждого файла
-   Предпросмотр перед загрузкой
-   Валидация файлов (MIME тип из `allowed_mimes`, max размер: 25 МБ)
-   Выбор коллекции (опционально, regex: `^[a-z0-9-_.]+$`, max 64 символа)
-   Поддержка только разрешенных форматов: JPEG, PNG, WebP, GIF, MP4, MP3, PDF

**Файлы:**

-   `src/components/media/MediaUploader.tsx`

**Пропсы:**

-   `multiple?: boolean` - множественная загрузка
-   `accept?: string` - допустимые типы файлов
-   `maxSize?: number` - максимальный размер
-   `collection?: string` - коллекция по умолчанию

**Пропсы для обработчиков:**

-   `onUploadSuccess?: (media: Media) => void` - успешная загрузка
-   `onUploadError?: (error: Error) => void` - ошибка загрузки
-   `onUploadProgress?: (progress: number) => void` - прогресс загрузки

**Критерии приёмки:**

-   Валидация до начала загрузки (MIME тип, размер файла)
-   Отображение прогресса для каждого файла
-   Обработка ошибок с детальными сообщениями (422 validation error, 429 rate limit)
-   Поддержка больших файлов до 25 МБ (настраивается через `MEDIA_MAX_UPLOAD_MB`)
-   Отправка multipart/form-data с полями: `file` (required), `title`, `alt`, `collection` (опционально)
-   Collection regex: `^[a-z0-9-_.]+$` (case-insensitive, max 64 символа)

---

#### Задача 10: Создание компонента MediaFilters

**Приоритет:** Средний  
**Сложность:** Средняя  
**Зависимости:** Задачи 1, 5

**Примечание:** Компонент использует `mediaStore.setFilters()` для обновления фильтров.

**Описание:**
Компонент фильтрации медиа:

-   Поиск по названию и исходному имени (`q` параметр, ищет в `title` и `original_name`)
-   Фильтр по типу (image, video, audio, document)
-   Фильтр по MIME-типу (prefix match, например `image/png`)
-   Фильтр по коллекции (slug, до 64 символов)
-   Управление удалёнными (with, only)
-   Сортировка по полям: `created_at`, `size_bytes`, `mime` (default: `created_at`)
-   Направление сортировки: `asc`, `desc` (default: `desc`)
-   Размер страницы (1-100, default: 15)

**Файлы:**

-   `src/components/media/MediaFilters.tsx`

**Пропсы:**

-   `filters: MediaFilters` - текущие фильтры

**Пропсы для обработчиков:**

-   `onFilterChange?: (filters: MediaFilters) => void` - изменение фильтров
-   `onReset?: () => void` - сброс фильтров

**Критерии приёмки:**

-   Реактивное применение фильтров
-   Сохранение фильтров в URL (query params) или localStorage
-   Визуальная индикация активных фильтров
-   Сброс к значениям по умолчанию

---

### Блок 3: Функциональность (11-15)

#### Задача 11: Реализация страницы Media Library

**Приоритет:** Высокий  
**Сложность:** Средняя  
**Зависимости:** Задачи 6, 9, 10

**Описание:**
Главная страница медиа-библиотеки:

-   Интеграция всех компонентов (MediaList, MediaFilters, MediaUploader)
-   Маршрутизация
-   Управление состоянием
-   Панель инструментов (toolbar)

**Файлы:**

-   `src/pages/media/MediaLibrary.tsx` или `src/pages/media/index.tsx`
-   `src/router/media.ts` (роутинг)

**Функциональность:**

-   Переключение между списком и загрузкой
-   Bulk операции (удаление выбранных)
-   Быстрый поиск
-   Сохранение состояния фильтров

**Критерии приёмки:**

-   Полная интеграция всех компонентов
-   Корректная навигация
-   Сохранение состояния при переходах
-   Responsive дизайн

---

#### Задача 12: Реализация редактирования метаданных

**Приоритет:** Средний  
**Сложность:** Низкая  
**Зависимости:** Задачи 1, 2, 8

**Описание:**
Форма редактирования метаданных медиа:

-   Поля: title, alt, collection
-   Валидация
-   Сохранение через API
-   Обработка ошибок

**Файлы:**

-   `src/components/media/MediaEditForm.tsx`

**Форма:**

-   Title (string, optional, max 255 символов)
-   Alt (string, optional, max 255 символов, для изображений)
-   Collection (string, optional, max 64 символа, regex: `^[a-z0-9-_.]+$` case-insensitive)

**Критерии приёмки:**

-   Валидация полей (title, alt: max 255 символов; collection: max 64 символа, regex: `^[a-z0-9-_.]+$` case-insensitive)
-   Отображение ошибок валидации (422)
-   Оптимистичное обновление UI
-   Обратная связь пользователю (toast/notification)
-   Все поля опциональны (nullable)

---

#### Задача 13: Реализация удаления и восстановления

**Приоритет:** Средний  
**Сложность:** Низкая  
**Зависимости:** Задачи 1, 2, 5

**Примечание:** Использует методы `mediaStore.deleteMedia()` и `mediaStore.restoreMedia()`.

**Описание:**
Логика удаления и восстановления медиа:

-   Подтверждение удаления
-   Обработка ошибки 409 (Media in use)
-   Отображение связанных записей при ошибке
-   Восстановление из корзины
-   Обновление списка после операций

**Файлы:**

-   Интеграция в компоненты (MediaCard, MediaDetails)
-   Модальное окно подтверждения

**Критерии приёмки:**

-   Модальное окно с предупреждением
-   Отображение связанных записей при ошибке 409 (Media in use)
-   Обработка структуры ошибки: `meta.references` содержит массив `{ entry_id: number, title: string }`
-   Автоматическое обновление UI
-   Корректная обработка всех кодов ошибок (404, 409, 429)
-   Восстановление работает только для soft-deleted медиа (404 если не удалено)

---

#### Задача 14: Реализация скачивания медиа

**Приоритет:** Низкий  
**Сложность:** Низкая  
**Зависимости:** Задачи 1, 2

**Описание:**
Функционал скачивания медиафайлов:

-   Кнопка скачивания в карточке/деталях
-   Использование download_url из API
-   Обработка ошибок

**Файлы:**

-   Утилита `src/utils/media.ts` (метод `downloadMedia`) или метод в `mediaStore`

**Критерии приёмки:**

-   Корректное скачивание файла (download endpoint возвращает 302 редирект на подписанный URL)
-   Обработка ошибок (404, 500 - Media download error)
-   Индикатор загрузки для больших файлов

---

#### Задача 15: Реализация поиска и фильтрации

**Приоритет:** Высокий  
**Сложность:** Средняя  
**Зависимости:** Задачи 5, 10

**Примечание:** Интеграция с `mediaStore.setFilters()` и `mediaStore.loadMediaList()`.

**Описание:**
Интеграция поиска и фильтрации:

-   Debounce для поиска
-   Синхронизация фильтров с URL
-   Сохранение фильтров в localStorage
-   Быстрые фильтры (часто используемые)

**Файлы:**

-   Интеграция в MediaFilters и MediaLibrary

**Критерии приёмки:**

-   Debounce 300-500ms для поиска (поиск идет по `title` и `original_name`)
-   URL параметры отражают фильтры
-   Сохранение состояния между сессиями (localStorage)
-   Быстрый отклик на изменения
-   Поддержка всех фильтров: `q`, `kind`, `mime`, `collection`, `deleted`, `sort`, `order`, `per_page`

---

### Блок 4: Дополнительные функции (16-20)

#### Задача 16: Управление коллекциями

**Приоритет:** Средний  
**Сложность:** Средняя  
**Зависимости:** Задачи 1, 2, 12

**Описание:**
Интерфейс для работы с коллекциями:

-   Список коллекций
-   Создание новой коллекции (если API поддерживает)
-   Перемещение медиа между коллекциями
-   Фильтрация по коллекции
-   Группировка в UI по коллекциям (опционально)

**Файлы:**

-   `src/components/media/CollectionSelector.tsx`
-   Методы в `mediaStore` для работы с коллекциями (если есть API для коллекций)

**Критерии приёмки:**

-   Выбор коллекции при загрузке
-   Изменение коллекции через редактирование
-   Фильтрация по коллекции работает корректно
-   Визуальная индикация коллекции
-   Regex для коллекции: `^[a-z0-9-_.]+$` (case-insensitive, max 64 символа)

---

#### Задача 17: Интеграция с Entry (привязка медиа)

**Приоритет:** Средний  
**Сложность:** Высокая  
**Зависимости:** Задачи 1, 6

**Описание:**
Функционал привязки медиа к записям (Entry):

-   Выбор медиа при редактировании Entry
-   Отображение привязанных медиа в Entry
-   Media picker компонент
-   Управление порядком привязанных медиа

**Файлы:**

-   `src/components/media/MediaPicker.tsx`
-   Интеграция в форму редактирования Entry

**Критерии приёмки:**

-   Выбор нескольких медиа
-   Drag & drop для изменения порядка
-   Отображение превью выбранных медиа
-   Сохранение связи через API Entry

---

#### Задача 18: Оптимизация производительности

**Приоритет:** Средний  
**Сложность:** Средняя  
**Зависимости:** Все предыдущие задачи

**Описание:**
Оптимизация производительности:

-   Lazy loading изображений
-   Виртуализация списка (для больших списков)
-   Кэширование API запросов
-   Debounce/throttle для поиска и фильтров
-   Оптимизация перерисовок (memoization)

**Файлы:**

-   Оптимизации в существующих компонентах

**Критерии приёмки:**

-   Плавный скролл при 100+ элементах
-   Минимальные повторные запросы к API
-   Быстрый отклик UI
-   Оптимизация bundle size

---

#### Задача 19: Тестирование компонентов

**Приоритет:** Высокий  
**Сложность:** Высокая  
**Зависимости:** Все предыдущие задачи

**Описание:**
Написание тестов для всех компонентов и логики:

-   Unit тесты для утилит
-   Unit тесты для MobX stores
-   Component тесты
-   Integration тесты для API клиента
-   E2E тесты для критических сценариев

**Файлы:**

-   `src/utils/__tests__/media.test.ts`
-   `src/stores/__tests__/mediaStore.test.ts`
-   `src/components/media/__tests__/MediaCard.test.ts`
-   и т.д.

**Покрытие:**

-   Утилиты: 100%
-   Компоненты: 80%+ критического функционала
-   API клиент: все методы
-   Основные пользовательские сценарии

**Критерии приёмки:**

-   Все тесты проходят
-   Покрытие соответствует требованиям
-   Тесты изолированы (mock API)
-   Тесты поддерживаются и обновляются

---

#### Задача 20: Документация и финальная полировка

**Приоритет:** Средний  
**Сложность:** Низкая  
**Зависимости:** Все предыдущие задачи

**Описание:**
Финализация проекта:

-   Документация компонентов (Storybook или аналог)
-   Документация API клиента
-   Документация MobX stores
-   Примеры использования
-   Accessibility audit
-   Cross-browser testing
-   Performance audit

**Файлы:**

-   `docs/frontend/media-components.md`
-   Storybook stories (если используется)

**Критерии приёмки:**

-   Вся документация актуальна
-   Примеры работают
-   Accessibility соответствует WCAG 2.1 AA
-   Работает во всех поддерживаемых браузерах
-   Производительность соответствует целям

---

## Приоритизация

### Фаза 1 (MVP): Задачи 1-6, 9, 11, 15

Базовый функционал: типы, API клиент, MobX store, список, загрузка, фильтры.

### Фаза 2 (Расширение): Задачи 7-8, 12-14

Детали, редактирование, удаление, скачивание.

### Фаза 3 (Интеграция): Задачи 16-17

Коллекции и интеграция с Entry.

### Фаза 4 (Полировка): Задачи 18-20

Оптимизация, тесты, документация.

---

## Технический стек

-   **Framework:** React
-   **TypeScript:** Обязательно
-   **State Management:** MobX 6 (makeAutoObservable / makeObservable)
-   **HTTP Client:** Axios / Fetch API
-   **Styling:** TailwindCSS (уже используется)
-   **Testing:** Vitest / Jest
-   **E2E:** Playwright / Cypress

**Примечание:** Используется MobX 6 с паттерном makeAutoObservable для автоматического создания observable/action/computed.

### Дополнительные детали API

**Пагинация:**

-   Default `per_page`: 15
-   Диапазон: 1-100
-   Формат ответа: Laravel pagination с `data`, `links`, `meta`
-   Структура `links`: `first`, `last`, `prev`, `next` (могут быть null)
-   Структура `meta`: `current_page`, `from`, `last_page`, `path`, `per_page`, `to`, `total`

**Поиск:**

-   Параметр `q`: поиск по полям `title` и `original_name` (LIKE запрос, case-insensitive)
-   Максимальная длина: 255 символов

**Сортировка:**

-   Поля: `created_at` (default), `size_bytes`, `mime`
-   Направление: `asc`, `desc` (default: `desc`)

**Preview URLs:**

-   Доступны только для изображений (`kind === 'image'`)
-   Варианты: `thumbnail` (max 320px), `medium` (max 1024px)
-   В ответе API (`preview_urls`) содержатся полные URL endpoints (не подписанные URLs)
-   При обращении к preview endpoint возвращается 302 редирект на подписанный URL хранилища
-   TTL подписанного URL: 300 секунд (настраивается через `MEDIA_SIGNED_TTL`)
-   Default variant: `thumbnail` (если не указан в query параметре)

**Download URL:**

-   Endpoint возвращает 302 редирект на подписанный URL хранилища
-   В ответе API (`download_url`) содержится полный URL endpoint (не подписанный URL)
-   TTL подписанного URL: 300 секунд (настраивается через `MEDIA_SIGNED_TTL`)
-   Доступен для всех типов медиа (image, video, audio, document)

**Ошибки:**

-   `401` - Unauthorized (требуется аутентификация)
-   `404` - Media not found
-   `409` - Media in use (при удалении, если медиа используется в записях)
-   `422` - Validation error (невалидные данные)
-   `429` - Rate limit exceeded (throttle: 60 req/min для GET, 20 req/min для POST/PUT/DELETE)
-   `500` - Media variant error (ошибка генерации варианта превью для preview endpoint)
-   `500` - Media download error (ошибка генерации подписанного URL для download endpoint)

**Формат ошибок:**
Все ошибки возвращаются в формате Problem Details (RFC 7807) с полями:

-   `type` - URI типа ошибки
-   `title` - краткое описание
-   `status` - HTTP статус код
-   `code` - код ошибки (enum)
-   `detail` - детальное описание
-   `meta` - дополнительная информация (errors, references, request_id, etc.)
-   `trace_id` - идентификатор трейса для отладки

---

## Связанные документы

-   `app/Http/Controllers/Admin/V1/MediaController.php` - API контроллер
-   `app/Http/Resources/MediaResource.php` - Структура ответов API
-   `app/Models/Media.php` - Модель данных
-   `docs/generated/http-endpoints.md` - Документация API endpoints

---

**Статус:** План обновлен и соответствует бэкенду API  
**Последнее обновление:** 2025-01-15

**Изменения v1.2:**

-   Уточнен regex для коллекции: `^[a-z0-9-_.]+$` (case-insensitive)
-   Добавлена информация об ошибке 500 для download endpoint (Media download error)
-   Уточнены детали валидации collection (case-insensitive regex)

**Изменения v1.1:**

-   Обновлены типы медиа: указаны только поддерживаемые форматы (JPEG, PNG, GIF, WebP, MP4, MP3, PDF)
-   Добавлены детали о поддерживаемых MIME-типах из конфигурации
-   Уточнены параметры пагинации (default: 15, диапазон: 1-100)
-   Добавлены детали о preview URLs (только для изображений, варианты: thumbnail, medium)
-   Уточнены детали поиска (по полям `title` и `original_name`)
-   Добавлены детали о валидации полей и ограничениях
-   Добавлены детали об обработке ошибок (401, 404, 409, 422, 429, 500)
-   Уточнены детали о формате ошибок (Problem Details RFC 7807)
-   Добавлены детали о rate limiting (60 req/min для GET, 20 req/min для POST/PUT/DELETE)
-   Уточнены детали о preview endpoint (302 редирект на подписанный URL)
-   Исправлены несоответствия с реальным API бэкенда
