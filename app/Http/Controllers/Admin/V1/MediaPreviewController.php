<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\Media\Services\OnDemandVariantService;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Контроллер для предпросмотра медиа-файлов в админ-панели.
 *
 * Предоставляет временные подписанные URL для предпросмотра вариантов изображений
 * (thumbnails, resized) с автоматической генерацией вариантов по требованию.
 *
 * @package App\Http\Controllers\Admin\V1
 */
class MediaPreviewController extends Controller
{
    use AuthorizesRequests;
    use ThrowsErrors;

    /**
     * @param \App\Domain\Media\Services\OnDemandVariantService $variantService Сервис для генерации вариантов
     */
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "0b0b0b0b-b7cb-6c30-033f-3f5e0b0b0b01",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-0b0b0b0bb7cb6c30033f3f5e0b0b0b01-0b0b0b0bb7cb6c30-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Media not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Media with ID uuid-media does not exist.",
     *   "meta": {
     *     "request_id": "0b0b0b0b-b7cb-6c30-033f-3f5e0b0b0b02",
     *     "media_id": "uuid-media"
     *   },
     *   "trace_id": "00-0b0b0b0bb7cb6c30033f3f5e0b0b0b02-0b0b0b0bb7cb6c30-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "Variant foo is not configured.",
     *   "meta": {
     *     "request_id": "0b0b0b0b-b7cb-6c30-033f-3f5e0b0b0b03",
     *     "variant": "foo"
     *   },
     *   "trace_id": "00-0b0b0b0bb7cb6c30033f3f5e0b0b0b03-0b0b0b0bb7cb6c30-01"
     * }
     * @response status=500 {
     *   "type": "https://stupidcms.dev/problems/media-variant-error",
     *   "title": "Failed to generate media variant",
     *   "status": 500,
     *   "code": "MEDIA_VARIANT_ERROR",
     *   "detail": "Failed to generate media variant.",
     *   "meta": {
     *     "request_id": "0b0b0b0b-b7cb-6c30-033f-3f5e0b0b0b04",
     *     "variant": "thumbnail"
     *   },
     *   "trace_id": "00-0b0b0b0bb7cb6c30033f3f5e0b0b0b04-0b0b0b0bb7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "0b0b0b0b-b7cb-6c30-033f-3f5e0b0b0b05",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-0b0b0b0bb7cb6c30033f3f5e0b0b0b05-0b0b0b0bb7cb6c30-01"
     * }
     */
    public function preview(Request $request, string $mediaId): RedirectResponse
    {
        $variant = $request->query('variant', 'thumbnail');

        $media = Media::withTrashed()->find($mediaId);

        if (! $media) {
            $this->throwMediaNotFound($mediaId);
        }

        $this->authorize('view', $media);

        try {
            $variantModel = $this->variantService->ensureVariant($media, $variant);
        } catch (InvalidArgumentException $exception) {
            $this->throwError(
                ErrorCode::VALIDATION_ERROR,
                $exception->getMessage(),
                [
                    'variant' => $variant,
                ],
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->throwError(
                ErrorCode::MEDIA_VARIANT_ERROR,
                'Failed to generate media variant.',
                ['variant' => $variant],
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "0b0b0b0b-b7cb-6c30-033f-3f5e0b0b0b06",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-0b0b0b0bb7cb6c30033f3f5e0b0b0b06-0b0b0b0bb7cb6c30-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Media not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Media with ID uuid-media does not exist.",
     *   "meta": {
     *     "request_id": "0b0b0b0b-b7cb-6c30-033f-3f5e0b0b0b07",
     *     "media_id": "uuid-media"
     *   },
     *   "trace_id": "00-0b0b0b0bb7cb6c30033f3f5e0b0b0b07-0b0b0b0bb7cb6c30-01"
     * }
     * @response status=500 {
     *   "type": "https://stupidcms.dev/problems/media-download-error",
     *   "title": "Failed to generate download URL",
     *   "status": 500,
     *   "code": "MEDIA_DOWNLOAD_ERROR",
     *   "detail": "Failed to generate download URL.",
     *   "meta": {
     *     "request_id": "0b0b0b0b-b7cb-6c30-033f-3f5e0b0b0b08",
     *     "media_id": "uuid-media"
     *   },
     *   "trace_id": "00-0b0b0b0bb7cb6c30033f3f5e0b0b0b08-0b0b0b0bb7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "0b0b0b0b-b7cb-6c30-033f-3f5e0b0b0b09",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-0b0b0b0bb7cb6c30033f3f5e0b0b0b09-0b0b0b0bb7cb6c30-01"
     * }
     */
    public function download(string $mediaId): RedirectResponse
    {
        $media = Media::withTrashed()->find($mediaId);

        if (! $media) {
            $this->throwMediaNotFound($mediaId);
        }

        $this->authorize('view', $media);

        try {
            $url = $this->temporaryUrl($media->disk, $media->path);
        } catch (Throwable $exception) {
            report($exception);

            $this->throwError(
                ErrorCode::MEDIA_DOWNLOAD_ERROR,
                'Failed to generate download URL.',
                ['media_id' => $mediaId],
            );
        }

        return redirect()->away($url);
    }

    /**
     * Создать временный подписанный URL для медиа-файла.
     *
     * Пытается создать временный URL с TTL из конфига. При ошибке
     * возвращает обычный URL.
     *
     * @param string $diskName Имя диска (storage)
     * @param string $path Путь к файлу
     * @return string URL для доступа к файлу
     * @throws \InvalidArgumentException Если не удалось создать URL
     */
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

    /**
     * Выбросить ошибку "медиа не найдено".
     *
     * @param string $mediaId ID медиа-файла
     * @return never
     */
    private function throwMediaNotFound(string $mediaId): never
    {
        $this->throwError(
            ErrorCode::NOT_FOUND,
            sprintf('Media with ID %s does not exist.', $mediaId),
            ['media_id' => $mediaId],
        );
    }
}


