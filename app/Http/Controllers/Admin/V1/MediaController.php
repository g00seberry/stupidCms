<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\Media\Actions\MediaStoreAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\Media\IndexMediaRequest;
use App\Http\Requests\Admin\Media\StoreMediaRequest;
use App\Http\Requests\Admin\Media\UpdateMediaRequest;
use App\Http\Resources\Admin\MediaCollection;
use App\Http\Resources\MediaResource;
use App\Models\Entry;
use App\Models\Media;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MediaController extends Controller
{
    use Problems;
    use AuthorizesRequests;

    public function __construct(
        private readonly MediaStoreAction $storeAction
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
     *       "mime": "image/jpeg",
     *       "size_bytes": 235678,
     *       "preview_urls": {
     *         "thumbnail": "https://api.stupidcms.dev/api/v1/admin/media/uuid-media/preview?variant=thumbnail"
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
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function index(IndexMediaRequest $request): MediaCollection
    {
        $this->authorize('viewAny', Media::class);

        $validated = $request->validated();
        $query = Media::query();

        match ($validated['deleted'] ?? null) {
            'with' => $query->withTrashed(),
            'only' => $query->onlyTrashed(),
            default => null,
        };

        if (! empty($validated['q'])) {
            $term = $validated['q'];
            $query->where(function ($builder) use ($term) {
                $builder->where('title', 'like', "%{$term}%")
                    ->orWhere('original_name', 'like', "%{$term}%");
            });
        }

        if (! empty($validated['kind'])) {
            $kind = $validated['kind'];
            if ($kind === 'document') {
                $query->where(function ($builder) {
                    $builder->where('mime', 'not like', 'image/%')
                        ->where('mime', 'not like', 'video/%')
                        ->where('mime', 'not like', 'audio/%');
                });
            } else {
                $prefix = match ($kind) {
                    'image' => 'image/%',
                    'video' => 'video/%',
                    'audio' => 'audio/%',
                    default => null,
                };

                if ($prefix) {
                    $query->where('mime', 'like', $prefix);
                }
            }
        }

        if (! empty($validated['mime'])) {
            $query->where('mime', 'like', $validated['mime'].'%');
        }

        if (! empty($validated['collection'])) {
            $query->where('collection', $validated['collection']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $order = $validated['order'] ?? 'desc';

        $query->orderBy($sort, $order);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $perPage = max(1, min(100, $perPage));

        $paginator = $query->paginate($perPage)->appends($validated);

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
     * @response status=201 {
     *   "data": {
     *     "id": "uuid-media",
     *     "kind": "image",
     *     "name": "hero.jpg",
     *     "mime": "image/jpeg",
     *     "size_bytes": 235678,
     *     "title": "Hero image",
     *     "alt": "Hero cover",
     *     "collection": "uploads",
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
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation error",
     *   "status": 422,
     *   "detail": "The media payload failed validation constraints.",
     *   "errors": {
     *     "file": [
     *       "The file field is required."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function store(StoreMediaRequest $request): MediaResource
    {
        $this->authorize('create', Media::class);

        $validated = $request->validated();
        $file = $request->file('file');

        if (! $file) {
            throw new HttpResponseException(
                $this->problem(
                    422,
                    'Validation error',
                    'File payload is missing.',
                    [
                        'type' => 'https://stupidcms.dev/problems/validation-error',
                        'errors' => ['file' => ['File payload is required.']],
                    ]
                )
            );
        }

        $media = $this->storeAction->execute($file, $validated);

        return new MediaResource($media);
    }

    /**
     * Просмотр информации о медиафайле.
     *
     * @group Admin ▸ Media
     * @name Show media
     * @authenticated
     * @urlParam media string required UUID медиа. Example: uuid-media
     * @response status=200 {
     *   "data": {
     *     "id": "uuid-media",
     *     "kind": "image",
     *     "name": "hero.jpg",
     *     "mime": "image/jpeg",
     *     "deleted_at": null
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Media not found",
     *   "status": 404,
     *   "detail": "Media with ID uuid-media does not exist."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function show(string $mediaId): MediaResource
    {
        $media = Media::withTrashed()->find($mediaId);

        if (! $media) {
            $this->notFound($mediaId);
        }

        $this->authorize('view', $media);

        return new MediaResource($media);
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
     *     "title": "Updated hero image",
     *     "alt": "Updated hero cover",
     *     "collection": "uploads"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Media not found",
     *   "status": 404,
     *   "detail": "Media with ID uuid-media does not exist."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function update(UpdateMediaRequest $request, string $mediaId): MediaResource
    {
        $media = Media::withTrashed()->find($mediaId);

        if (! $media) {
            $this->notFound($mediaId);
        }

        $this->authorize('update', $media);

        $media->fill($request->validated());
        $media->save();

        return new MediaResource($media->fresh());
    }

    /**
     * Удаление медиа (soft delete).
     *
     * @group Admin ▸ Media
     * @name Delete media
     * @authenticated
     * @urlParam media string required UUID медиа. Example: uuid-media
     * @response status=204 {}
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Media not found",
     *   "status": 404,
     *   "detail": "Media with ID uuid-media does not exist."
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/media-in-use",
     *   "title": "Media in use",
     *   "status": 409,
     *   "detail": "Media is referenced by content and cannot be deleted.",
     *   "references": [
     *     {
     *       "entry_id": 42,
     *       "title": "Landing page"
     *     }
     *   ]
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function destroy(Request $request, string $mediaId): HttpResponse
    {
        $media = Media::query()->find($mediaId);

        if (! $media) {
            $this->notFound($mediaId);
        }

        $this->authorize('delete', $media);

        $references = Entry::query()
            ->select(['entries.id', 'entries.title'])
            ->whereHas('media', function ($q) use ($media) {
                $q->where('media.id', $media->id);
            })
            ->limit(3)
            ->get();

        if ($references->isNotEmpty()) {
            return $this->problem(
                409,
                'Media in use',
                'Media is referenced by content and cannot be deleted.',
                [
                    'type' => 'https://stupidcms.dev/problems/media-in-use',
                    'references' => $references->map(fn ($entry) => [
                        'entry_id' => $entry->id,
                        'title' => $entry->title,
                    ]),
                ]
            );
        }

        $media->delete();

        return response()
            ->noContent()
            ->header('Cache-Control', 'no-store, private')
            ->header('Vary', 'Cookie');
    }

    /**
     * Восстановление удалённого медиа.
     *
     * @group Admin ▸ Media
     * @name Restore media
     * @authenticated
     * @urlParam media string required UUID медиа. Example: uuid-media
     * @response status=200 {
     *   "data": {
     *     "id": "uuid-media",
     *     "deleted_at": null
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Media not found",
     *   "status": 404,
     *   "detail": "Deleted media with ID uuid-media does not exist."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function restore(Request $request, string $mediaId): MediaResource
    {
        $media = Media::onlyTrashed()->find($mediaId);

        if (! $media) {
            throw new HttpResponseException(
                $this->problem(
                    404,
                    'Media not found',
                    "Deleted media with ID {$mediaId} does not exist.",
                    ['type' => 'https://stupidcms.dev/problems/not-found']
                )
            );
        }

        $this->authorize('restore', $media);

        $media->restore();
        $media->refresh();

        return new MediaResource($media);
    }

    private function notFound(string $mediaId): never
    {
        throw new HttpResponseException(
            $this->problem(
                404,
                'Media not found',
                "Media with ID {$mediaId} does not exist.",
                ['type' => 'https://stupidcms.dev/problems/not-found']
            )
        );
    }
}


