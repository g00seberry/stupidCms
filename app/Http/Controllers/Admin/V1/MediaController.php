<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\Media\Actions\MediaStoreAction;
use App\Domain\Media\Actions\ListMediaAction;
use App\Domain\Media\Actions\UpdateMediaMetadataAction;
use App\Domain\Media\Actions\MediaForceDeleteAction;
use App\Domain\Media\EloquentMediaRepository;
use App\Domain\Media\Events\MediaDeleted;
use App\Domain\Media\MediaDeletedFilter;
use App\Domain\Media\MediaQuery;
use App\Domain\Media\MediaRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Media\BulkDeleteMediaRequest;
use App\Http\Requests\Admin\Media\BulkForceDeleteMediaRequest;
use App\Http\Requests\Admin\Media\BulkRestoreMediaRequest;
use App\Http\Requests\Admin\Media\IndexMediaRequest;
use App\Http\Requests\Admin\Media\StoreMediaRequest;
use App\Http\Requests\Admin\Media\UpdateMediaRequest;
use App\Http\Resources\Admin\MediaCollection;
use App\Http\Resources\Media\BaseMediaResource;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use App\Support\Http\AdminResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Контроллер для управления медиа-файлами в админ-панели.
 *
 * Предоставляет CRUD операции для медиа-файлов: загрузка, просмотр, обновление,
 * массовое мягкое удаление, массовое окончательное удаление, массовое восстановление,
 * управление вариантами и привязкой к записям.
 *
 * @package App\Http\Controllers\Admin\V1
 */
class MediaController extends Controller
{
    use ThrowsErrors;
    use AuthorizesRequests;

    /**
     * @param \App\Domain\Media\Actions\MediaStoreAction $storeAction Действие для сохранения медиа-файлов
     * @param \App\Domain\Media\Actions\ListMediaAction $listAction Действие для выборки медиа
     * @param \App\Domain\Media\Actions\UpdateMediaMetadataAction $updateMetadataAction Действие для обновления метаданных
     * @param \App\Domain\Media\Actions\MediaForceDeleteAction $forceDeleteAction Действие для окончательного удаления медиа
     */
    public function __construct(
        private readonly MediaStoreAction $storeAction,
        private readonly ListMediaAction $listAction,
        private readonly UpdateMediaMetadataAction $updateMetadataAction,
        private readonly MediaForceDeleteAction $forceDeleteAction,
    ) {
    }

    /**
     * Список медиафайлов с фильтрами.
     *
     * @group Admin ▸ Media
     * @name List media
     * @authenticated
     * @queryParam q string Поиск по названию и исходному имени (<=255). Example: hero
     * @queryParam kind string Фильтр по типу. Values: image,video,audio,document.
     * @queryParam mime string Фильтр по MIME (prefix match). Example: image/png
     * @queryParam collection string Коллекция (slug, до 64 символов). Example: uploads
     * @queryParam deleted string Управление soft-deleted. Values: with,only.
     * @queryParam sort string Поле сортировки. Values: created_at,size_bytes,mime. Default: created_at.
     * @queryParam order string Направление сортировки. Values: asc,desc. Default: desc.
     * @queryParam per_page int Размер страницы (1-100). Default: 15.
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": "uuid-media",
     *       "kind": "image",
     *       "name": "hero.jpg",
     *       "ext": "jpg",
     *       "mime": "image/jpeg",
     *       "size_bytes": 235678,
     *       "width": 1920,
     *       "height": 1080,
     *       "title": "Hero image",
     *       "alt": "Hero cover",
     *       "collection": "uploads",
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:00:00+00:00",
     *       "deleted_at": null,
     *       "preview_urls": {
     *         "thumbnail": "https://api.stupidcms.dev/api/v1/admin/media/uuid-media/preview?variant=thumbnail",
     *         "medium": "https://api.stupidcms.dev/api/v1/admin/media/uuid-media/preview?variant=medium",
     *         "large": "https://api.stupidcms.dev/api/v1/admin/media/uuid-media/preview?variant=large"
     *       },
     *       "download_url": "https://api.stupidcms.dev/api/v1/admin/media/uuid-media/download"
     *     }
     *   ],
     *   "links": {
     *     "first": "…",
     *     "last": "…",
     *     "prev": null,
     *     "next": null
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 1,
     *     "per_page": 15,
     *     "total": 1
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555555",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-11111111222233334444555555555555-1111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "66666666-7777-8888-9999-000000000000",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-66666666777788889999000000000000-6666666677778888-01"
     * }
     */
    public function index(IndexMediaRequest $request): MediaCollection
    {
        $this->authorize('viewAny', Media::class);

        $v = $request->validated();
        $deletedFilter = match ($v['deleted'] ?? null) {
            'with' => MediaDeletedFilter::WithDeleted,
            'only' => MediaDeletedFilter::OnlyDeleted,
            default => MediaDeletedFilter::DefaultOnlyNotDeleted,
        };

        $sort = $v['sort'] ?? 'created_at';
        $order = $v['order'] ?? 'desc';
        $perPage = (int) ($v['per_page'] ?? 15);
        $perPage = max(1, min(100, $perPage));
        $page = (int) ($v['page'] ?? (int) ($request->query('page', 1)));

        $mq = new MediaQuery(
            search: $v['q'] ?? null,
            kind: $v['kind'] ?? null,
            mimePrefix: $v['mime'] ?? null,
            collection: $v['collection'] ?? null,
            deletedFilter: $deletedFilter,
            sort: $sort,
            order: $order,
            page: $page,
            perPage: $perPage,
        );

        $paginator = $this->listAction->execute($mq)->appends($v);
        
        // Загружаем связи для избежания N+1 проблем
        $paginator->getCollection()->load(['image', 'avMetadata']);

        return new MediaCollection($paginator);
    }

    /**
     * Загрузка нового медиафайла.
     *
     * @group Admin ▸ Media
     * @name Upload media
     * @authenticated
     * @bodyParam file file required Файл (mimetype из `config('media.allowed_mimes')`). Example: storage/app/scribe/examples/media-upload.png
     * @bodyParam title string Пользовательский заголовок. Example: Hero image
     * @bodyParam alt string Alt-текст для изображений. Example: Hero cover
     * @bodyParam collection string Коллекция (slug). Example: uploads
     * @responseHeader Cache-Control "no-store, private"
     * @responseHeader Vary "Cookie"
     * @response status=201 scenario="Изображение" {
     *   "data": {
     *     "id": "uuid-media",
     *     "kind": "image",
     *     "name": "hero.jpg",
     *     "ext": "jpg",
     *     "mime": "image/jpeg",
     *     "size_bytes": 235678,
     *     "width": 1920,
     *     "height": 1080,
     *     "title": "Hero image",
     *     "alt": "Hero cover",
     *     "collection": "uploads",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00",
     *     "deleted_at": null,
     *     "preview_urls": {
     *       "thumbnail": "https://api.stupidcms.dev/api/v1/admin/media/uuid-media/preview?variant=thumbnail"
     *     },
     *     "download_url": "https://api.stupidcms.dev/api/v1/admin/media/uuid-media/download"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555556",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-11111111222233334444555555555556-1111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The media payload failed validation constraints.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555557",
     *     "errors": {
     *       "file": [
     *         "The file field is required."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-11111111222233334444555555555557-1111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "66666666-7777-8888-9999-000000000001",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-66666666777788889999000000000001-6666666677778888-01"
     * }
     */
    /**
     * Загрузка нового медиа-файла.
     *
     * Сохраняет файл на диск, извлекает метаданные и создает запись Media в БД.
     * Возвращает специализированный ресурс в зависимости от типа медиа:
     * - MediaImageResource для изображений (с width, height, preview_urls)
     * - MediaVideoResource для видео (с duration_ms, bitrate_kbps, frame_rate и т.д.)
     * - MediaAudioResource для аудио (с duration_ms, bitrate_kbps, audio_codec)
     * - MediaDocumentResource для документов (только базовые поля)
     *
     * При дедупликации (файл с таким же checksum уже существует) возвращает 200,
     * при создании новой записи - 201.
     *
     * @param \App\Http\Requests\Admin\Media\StoreMediaRequest $request HTTP запрос с файлом и метаданными
     * @return \Illuminate\Http\JsonResponse JSON ответ со специализированным ресурсом медиа-файла
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на создание
     * @throws \App\Domain\Media\Validation\MediaValidationException Если файл не прошел валидацию
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $validated = $request->validated();
        $file = $request->file('file');

        if (! $file) {
            $this->throwError(
                ErrorCode::VALIDATION_ERROR,
                'File payload is missing.',
                [
                    'errors' => [
                        'file' => ['File payload is required.'],
                    ],
                ],
            );
        }

        $media = $this->storeAction->execute($file, $validated);
        
        // Загружаем связи для MediaResource (width, height из image, duration_ms из avMetadata)
        $media->load(['image', 'avMetadata']);

        // При дедупликации возвращаем 200, при создании - 201
        $statusCode = $media->wasRecentlyCreated ? HttpResponse::HTTP_CREATED : HttpResponse::HTTP_OK;

        return MediaResource::make($media)->response()->setStatusCode($statusCode);
    }

    /**
     * Просмотр информации о медиафайле.
     *
     * Возвращает специализированный ресурс в зависимости от типа медиа.
     * Структура ответа различается для разных типов:
     * - Изображения: width, height, preview_urls
     * - Видео: duration_ms, bitrate_kbps, frame_rate, frame_count, video_codec, audio_codec
     * - Аудио: duration_ms, bitrate_kbps, audio_codec
     * - Документы: только базовые поля
     *
     * @group Admin ▸ Media
     * @name Show media
     * @authenticated
     * @urlParam media string required UUID медиа. Example: uuid-media
     * @response status=200 scenario="Изображение" {
     *   "data": {
     *     "id": "uuid-media",
     *     "kind": "image",
     *     "name": "hero.jpg",
     *     "ext": "jpg",
     *     "mime": "image/jpeg",
     *     "size_bytes": 235678,
     *     "width": 1920,
     *     "height": 1080,
     *     "title": "Hero image",
     *     "alt": "Hero cover",
     *     "collection": "uploads",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00",
     *     "deleted_at": null,
     *     "preview_urls": {
     *       "thumbnail": "https://api.stupidcms.dev/api/v1/admin/media/uuid-media/preview?variant=thumbnail"
     *     },
     *     "download_url": "https://api.stupidcms.dev/api/v1/admin/media/uuid-media/download"
     *   }
     * }
     * @response status=200 scenario="Видео" {
     *   "data": {
     *     "id": "uuid-video",
     *     "kind": "video",
     *     "name": "video.mp4",
     *     "ext": "mp4",
     *     "mime": "video/mp4",
     *     "size_bytes": 5242880,
     *     "duration_ms": 120000,
     *     "bitrate_kbps": 3500,
     *     "frame_rate": 30,
     *     "frame_count": 3600,
     *     "video_codec": "h264",
     *     "audio_codec": "aac",
     *     "title": "Video title",
     *     "alt": null,
     *     "collection": "uploads",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00",
     *     "deleted_at": null,
     *     "download_url": "https://api.stupidcms.dev/api/v1/admin/media/uuid-video/download"
     *   }
     * }
     * @response status=200 scenario="Аудио" {
     *   "data": {
     *     "id": "uuid-audio",
     *     "kind": "audio",
     *     "name": "audio.mp3",
     *     "ext": "mp3",
     *     "mime": "audio/mpeg",
     *     "size_bytes": 3145728,
     *     "duration_ms": 180000,
     *     "bitrate_kbps": 256,
     *     "audio_codec": "mp3",
     *     "title": "Audio title",
     *     "alt": null,
     *     "collection": "uploads",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00",
     *     "deleted_at": null,
     *     "download_url": "https://api.stupidcms.dev/api/v1/admin/media/uuid-audio/download"
     *   }
     * }
     * @response status=200 scenario="Документ" {
     *   "data": {
     *     "id": "uuid-document",
     *     "kind": "document",
     *     "name": "document.pdf",
     *     "ext": "pdf",
     *     "mime": "application/pdf",
     *     "size_bytes": 102400,
     *     "title": "Document title",
     *     "alt": null,
     *     "collection": "uploads",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00",
     *     "deleted_at": null,
     *     "download_url": "https://api.stupidcms.dev/api/v1/admin/media/uuid-document/download"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555558",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-11111111222233334444555555555558-1111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Media not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Media with ID uuid-media does not exist.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555559",
     *     "media_id": "uuid-media"
     *   },
     *   "trace_id": "00-11111111222233334444555555555559-1111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "66666666-7777-8888-9999-000000000002",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-66666666777788889999000000000002-6666666677778888-01"
     * }
     */
    /**
     * Просмотр информации о медиа-файле.
     *
     * Возвращает специализированный ресурс в зависимости от типа медиа:
     * - MediaImageResource для изображений (с width, height, preview_urls)
     * - MediaVideoResource для видео (с duration_ms, bitrate_kbps, frame_rate и т.д.)
     * - MediaAudioResource для аудио (с duration_ms, bitrate_kbps, audio_codec)
     * - MediaDocumentResource для документов (только базовые поля)
     *
     * @param string $mediaId ULID идентификатор медиа-файла
     * @return \App\Http\Resources\Media\BaseMediaResource Специализированный ресурс медиа-файла
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на просмотр
     */
    public function show(string $mediaId): BaseMediaResource
    {
        $media = Media::withTrashed()->with(['image', 'avMetadata'])->find($mediaId);

        if (! $media) {
            $this->throwMediaNotFound($mediaId);
        }

        $this->authorize('view', $media);

        return MediaResource::make($media);
    }

    /**
     * Обновление метаданных медиа.
     *
     * @group Admin ▸ Media
     * @name Update media
     * @authenticated
     * @urlParam media string required UUID медиа. Example: uuid-media
     * @bodyParam title string Новый заголовок. Example: Updated hero image
     * @bodyParam alt string Alt-текст. Example: Updated hero cover
     * @bodyParam collection string Коллекция. Example: uploads
     * @response status=200 {
     *   "data": {
     *     "id": "uuid-media",
     *     "kind": "image",
     *     "name": "hero.jpg",
     *     "ext": "jpg",
     *     "mime": "image/jpeg",
     *     "size_bytes": 235678,
     *     "width": 1920,
     *     "height": 1080,
     *     "duration_ms": null,
     *     "title": "Updated hero image",
     *     "alt": "Updated hero cover",
     *     "collection": "uploads",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:05:00+00:00",
     *     "deleted_at": null,
     *     "preview_urls": {
     *       "thumbnail": "https://api.stupidcms.dev/api/v1/admin/media/uuid-media/preview?variant=thumbnail"
     *     },
     *     "download_url": "https://api.stupidcms.dev/api/v1/admin/media/uuid-media/download"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555560",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-11111111222233334444555555555660-1111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Media not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Media with ID uuid-media does not exist.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555561",
     *     "media_id": "uuid-media"
     *   },
     *   "trace_id": "00-11111111222233334444555555555661-1111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "66666666-7777-8888-9999-000000000003",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-66666666777788889999000000000003-6666666677778888-01"
     * }
     */
    /**
     * Обновление метаданных медиа-файла.
     *
     * Обновляет title, alt, collection и возвращает специализированный ресурс
     * в зависимости от типа медиа (изображение, видео, аудио, документ).
     *
     * @param \App\Http\Requests\Admin\Media\UpdateMediaRequest $request HTTP запрос с валидированными данными
     * @param string $mediaId ULID идентификатор медиа-файла
     * @return \App\Http\Resources\Media\BaseMediaResource Обновленный специализированный ресурс медиа-файла
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на обновление
     */
    public function update(UpdateMediaRequest $request, string $mediaId): BaseMediaResource
    {
        $media = Media::withTrashed()->find($mediaId);

        if (! $media) {
            $this->throwMediaNotFound($mediaId);
        }

        $this->authorize('update', $media);

        $updated = $this->updateMetadataAction->execute($mediaId, $request->validated());
        
        // Загружаем связи для MediaResource (width, height из image, duration_ms из avMetadata)
        $updated->load(['image', 'avMetadata']);
        
        return MediaResource::make($updated);
    }

    /**
     * Массовое мягкое удаление медиа-файлов.
     *
     * Выполняет мягкое удаление медиа-файлов по массиву идентификаторов
     * и отправляет событие MediaDeleted для каждого удалённого файла.
     *
     * @group Admin ▸ Media
     * @name Bulk delete media
     * @authenticated
     * @bodyParam ids array required Массив идентификаторов медиа-файлов (1-100 элементов). Example: ["01HXZYXQJ123456789ABCDEF", "01HXZYXQJ987654321FEDCBA"]
     * @bodyParam ids.* string required ULID идентификатор медиа-файла (26 символов). Example: 01HXZYXQJ123456789ABCDEF
     * @response status=204 {}
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555562",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-11111111222233334444555555555662-1111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The media payload failed validation constraints.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555563",
     *     "errors": {
     *       "ids": [
     *         "The ids field is required."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-11111111222233334444555555555663-1111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "66666666-7777-8888-9999-000000000004",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-66666666777788889999000000000004-6666666677778888-01"
     * }
     */
    public function bulkDestroy(BulkDeleteMediaRequest $request): HttpResponse
    {
        $ids = $request->validated()['ids'];
        $mediaItems = Media::query()->whereIn('id', $ids)->get();

        foreach ($mediaItems as $media) {
            $this->authorize('delete', $media);
            $media->delete();
            Event::dispatch(new MediaDeleted($media));
        }

        return AdminResponse::noContent();
    }

    /**
     * Массовое восстановление удалённых медиа-файлов.
     *
     * Выполняет восстановление мягко удалённых медиа-файлов по массиву идентификаторов.
     *
     * @group Admin ▸ Media
     * @name Bulk restore media
     * @authenticated
     * @bodyParam ids array required Массив идентификаторов удалённых медиа-файлов (1-100 элементов). Example: ["01HXZYXQJ123456789ABCDEF", "01HXZYXQJ987654321FEDCBA"]
     * @bodyParam ids.* string required ULID идентификатор медиа-файла (26 символов). Example: 01HXZYXQJ123456789ABCDEF
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": "01HXZYXQJ123456789ABCDEF",
     *       "kind": "image",
     *       "name": "hero.jpg",
     *       "ext": "jpg",
     *       "mime": "image/jpeg",
     *       "size_bytes": 235678,
     *       "width": 1920,
     *       "height": 1080,
     *       "title": "Hero image",
     *       "alt": "Hero cover",
     *       "collection": "uploads",
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:00:00+00:00",
     *       "deleted_at": null,
     *       "preview_urls": {
     *         "thumbnail": "https://api.stupidcms.dev/api/v1/admin/media/01HXZYXQJ123456789ABCDEF/preview?variant=thumbnail"
     *       },
     *       "download_url": "https://api.stupidcms.dev/api/v1/admin/media/01HXZYXQJ123456789ABCDEF/download"
     *     }
     *   ]
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555565",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-11111111222233334444555555555665-1111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The media payload failed validation constraints.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555566",
     *     "errors": {
     *       "ids": [
     *         "The ids field is required."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-11111111222233334444555555555666-1111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "66666666-7777-8888-9999-000000000005",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-66666666777788889999000000000005-6666666677778888-01"
     * }
     */
    /**
     * Массовое восстановление удаленных медиа-файлов.
     *
     * Восстанавливает мягко удаленные медиа-файлы по массиву идентификаторов.
     * Возвращает коллекцию с специализированными ресурсами для каждого типа медиа.
     *
     * @param \App\Http\Requests\Admin\Media\BulkRestoreMediaRequest $request HTTP запрос с массивом ids
     * @return \App\Http\Resources\Admin\MediaCollection Коллекция восстановленных медиа-файлов
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на восстановление
     */
    public function bulkRestore(BulkRestoreMediaRequest $request): MediaCollection
    {
        $ids = $request->validated()['ids'];
        $mediaItems = Media::onlyTrashed()->whereIn('id', $ids)->get();

        $restoredMedia = [];

        foreach ($mediaItems as $media) {
            $this->authorize('restore', $media);
            $media->restore();
            $media->refresh();
            
            // Загружаем связи для MediaResource (width, height из image, duration_ms из avMetadata)
            $media->load(['image', 'avMetadata']);
            
            $restoredMedia[] = $media;
        }

        return new MediaCollection($restoredMedia);
    }

    /**
     * Массовое окончательное удаление медиа-файлов.
     *
     * Выполняет полное удаление медиа-файлов по массиву идентификаторов:
     * удаляет физические файлы (основной файл и все варианты) с диска,
     * затем удаляет записи из БД. Операция необратима.
     *
     * @group Admin ▸ Media
     * @name Bulk force delete media
     * @authenticated
     * @bodyParam ids array required Массив идентификаторов медиа-файлов (1-100 элементов). Example: ["01HXZYXQJ123456789ABCDEF", "01HXZYXQJ987654321FEDCBA"]
     * @bodyParam ids.* string required ULID идентификатор медиа-файла (26 символов). Example: 01HXZYXQJ123456789ABCDEF
     * @response status=204 {}
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555567",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-11111111222233334444555555555667-1111111122223333-01"
     * }
     * @response status=403 {
     *   "type": "https://stupidcms.dev/problems/forbidden",
     *   "title": "Forbidden",
     *   "status": 403,
     *   "code": "FORBIDDEN",
     *   "detail": "You do not have permission to force delete media.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555568"
     *   },
     *   "trace_id": "00-11111111222233334444555555555668-1111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The media payload failed validation constraints.",
     *   "meta": {
     *     "request_id": "11111111-2222-3333-4444-555555555569",
     *     "errors": {
     *       "ids": [
     *         "The ids field is required."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-11111111222233334444555555555669-1111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "66666666-7777-8888-9999-000000000006",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-66666666777788889999000000000006-6666666677778888-01"
     * }
     */
    public function bulkForceDestroy(BulkForceDeleteMediaRequest $request): HttpResponse
    {
        $ids = $request->validated()['ids'];
        $mediaItems = Media::withTrashed()->whereIn('id', $ids)->get();

        foreach ($mediaItems as $media) {
            $this->authorize('forceDelete', $media);
            $this->forceDeleteAction->execute($media);
        }

        return AdminResponse::noContent();
    }

    private function notFound(string $mediaId): never
    {
        $this->throwMediaNotFound($mediaId);
    }

    private function throwMediaNotFound(string $mediaId, ?string $prefix = null): never
    {
        $detail = $prefix
            ? sprintf('%s media with ID %s does not exist.', ucfirst($prefix), $mediaId)
            : sprintf('Media with ID %s does not exist.', $mediaId);

        $this->throwError(
            ErrorCode::NOT_FOUND,
            $detail,
            [
                'media_id' => $mediaId,
                'prefix' => $prefix,
            ],
        );
    }
}


