<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\Media\Services\OnDemandVariantService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Models\Media;
use App\Support\Http\ProblemType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class MediaPreviewController extends Controller
{
    use Problems;
    use AuthorizesRequests;

    public function __construct(
        private readonly OnDemandVariantService $variantService
    ) {
    }

    /**
     * Генерация временного предпросмотра для изображения.
     *
     * @group Admin ▸ Media
     * @name Preview media
     * @authenticated
     * @urlParam media string required UUID медиа. Example: uuid-media
     * @queryParam variant string Вариант изображения. Default: thumbnail.
     * @responseHeader Location "https://cdn.stupidcms.dev/...signed..."
     * @response status=302 {}
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
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Invalid variant",
     *   "status": 422,
     *   "detail": "Variant foo is not configured."
     * }
     * @response status=500 {
     *   "type": "https://stupidcms.dev/problems/media-variant-error",
     *   "title": "Internal Server Error",
     *   "status": 500,
     *   "detail": "Failed to generate media variant."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function preview(Request $request, string $mediaId): RedirectResponse
    {
        $variant = $request->query('variant', 'thumbnail');

        $media = Media::withTrashed()->find($mediaId);

        if (! $media) {
            throw new HttpResponseException(
                $this->problem(
                    ProblemType::NOT_FOUND,
                    detail: "Media with ID {$mediaId} does not exist.",
                    title: 'Media not found'
                )
            );
        }

        $this->authorize('view', $media);

        try {
            $variantModel = $this->variantService->ensureVariant($media, $variant);
        } catch (InvalidArgumentException $exception) {
            throw new HttpResponseException(
                $this->problem(
                    ProblemType::VALIDATION_ERROR,
                    detail: $exception->getMessage(),
                    title: 'Invalid variant'
                )
            );
        } catch (Throwable $exception) {
            report($exception);

            throw new HttpResponseException(
                $this->problem(
                    ProblemType::MEDIA_VARIANT_ERROR,
                    detail: 'Failed to generate media variant.',
                    title: 'Internal Server Error'
                )
            );
        }

        $url = $this->temporaryUrl($media->disk, $variantModel->path);

        return redirect()->away($url);
    }

    /**
     * Получение временной ссылки на оригинал.
     *
     * @group Admin ▸ Media
     * @name Download media
     * @authenticated
     * @urlParam media string required UUID медиа. Example: uuid-media
     * @responseHeader Location "https://cdn.stupidcms.dev/...signed..."
     * @response status=302 {}
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
     * @response status=500 {
     *   "type": "https://stupidcms.dev/problems/media-download-error",
     *   "title": "Internal Server Error",
     *   "status": 500,
     *   "detail": "Failed to generate download URL."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function download(string $mediaId): RedirectResponse
    {
        $media = Media::withTrashed()->find($mediaId);

        if (! $media) {
            throw new HttpResponseException(
                $this->problem(
                    ProblemType::NOT_FOUND,
                    detail: "Media with ID {$mediaId} does not exist.",
                    title: 'Media not found'
                )
            );
        }

        $this->authorize('view', $media);

        try {
            $url = $this->temporaryUrl($media->disk, $media->path);
        } catch (Throwable $exception) {
            report($exception);

            throw new HttpResponseException(
                $this->problem(
                    ProblemType::MEDIA_DOWNLOAD_ERROR,
                    detail: 'Failed to generate download URL.',
                    title: 'Internal Server Error'
                )
            );
        }

        return redirect()->away($url);
    }

    private function temporaryUrl(string $diskName, string $path): string
    {
        $disk = Storage::disk($diskName);
        $expiry = now('UTC')->addSeconds((int) config('media.signed_ttl', 300));

        try {
            return $disk->temporaryUrl($path, $expiry);
        } catch (Throwable) {
            $url = $disk->url($path);

            if (! $url) {
                throw new InvalidArgumentException('Unable to generate media URL.');
            }

            return $url;
        }
    }
}


