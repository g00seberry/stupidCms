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

    public function show(string $mediaId): MediaResource
    {
        $media = Media::withTrashed()->find($mediaId);

        if (! $media) {
            $this->notFound($mediaId);
        }

        $this->authorize('view', $media);

        return new MediaResource($media);
    }

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


