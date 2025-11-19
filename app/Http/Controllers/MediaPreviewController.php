<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Media\Services\OnDemandVariantService;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Контроллер для предпросмотра медиа-файлов (публичный с поддержкой админских функций).
 *
 * Предоставляет доступ к медиа-файлам и их вариантам (thumbnails, resized) для публичных
 * и админских запросов. Для админов поддерживает доступ к удаленным файлам (withTrashed).
 * Для публичных запросов доступны только активные (не удаленные) файлы.
 *
 * @package App\Http\Controllers
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
     * Получить медиа-файл или его вариант.
     *
     * Поддерживает:
     * - Оригинальные файлы (без параметра variant)
     * - Варианты изображений (с параметром variant, например: thumbnail, medium, large)
     *
     * Для админов (аутентифицированных пользователей):
     * - Доступ к удаленным файлам (withTrashed)
     * - Проверка прав доступа через Policy
     * - Аутентификация выполняется через OptionalJwtAuth middleware
     *
     * Для публичных запросов:
     * - Доступ только к активным (не удаленным) файлам
     * - Без проверки прав доступа
     *
     * Поиск медиа-файла выполняется по ULID идентификатору через where('id', $id)->first()
     * для обеспечения корректной работы с ULID в Laravel.
     *
     * @group Media
     * @name Get media or variant
     * @unauthenticated
     * @urlParam id string required ULID идентификатор медиа-файла. Example: 01HZYQNGQK74ZP6YVZ6E7SFJ2D
     * @queryParam variant string Вариант изображения (thumbnail, medium, large). Если не указан, возвращается оригинал.
     * @responseHeader X-URL-TTL "300"
     * @responseHeader X-URL-Expires-At "2025-01-10T12:05:00+00:00"
     * @responseHeader Location "https://cdn.stupidcms.dev/...signed..."
     * @response status=200 file
     * @response status=302 {}
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Media not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Media with ID 01HZYQNGQK74ZP6YVZ6E7SFJ2D does not exist.",
     *   "meta": {
     *     "request_id": "0c0c0c0c-0c0c-0c0c-0c0c-0c0c0c0c0c01",
     *     "media_id": "01HZYQNGQK74ZP6YVZ6E7SFJ2D"
     *   },
     *   "trace_id": "00-0c0c0c0c0c0c0c0c0c0c0c0c0c0c0c01-0c0c0c0c0c0c0c-01"
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
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param string $id ULID идентификатор медиа-файла
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function show(Request $request, string $id): RedirectResponse|BinaryFileResponse
    {
        $variant = $request->query('variant');

        $isAdmin = $this->isAdminRequest($request);

        // Для админов используем withTrashed, для публичных - только активные
        // Используем where('id', $id) для явного поиска по ULID
        $media = $isAdmin
            ? Media::withTrashed()->where('id', $id)->first()
            : Media::query()->where('id', $id)->first();

        if (! $media) {
            $this->throwMediaNotFound($id);
        }

        // Для публичных запросов проверяем, что файл не удален
        if (! $isAdmin && $media->trashed()) {
            $this->throwMediaNotFound($id);
        }

        // Для админов проверяем права доступа
        if ($isAdmin) {
            $this->authorize('view', $media);
        }

        // Если указан вариант, генерируем/получаем вариант
        if ($variant !== null) {
            return $this->serveVariant($media, $variant);
        }

        // Иначе возвращаем оригинал
        try {
            return $this->serveFile($media->disk, $media->path, $media->mime, $isAdmin);
        } catch (Throwable $exception) {
            report($exception);

            $this->throwError(
                ErrorCode::MEDIA_DOWNLOAD_ERROR,
                'Failed to generate media URL.',
                ['media_id' => $id],
            );
        }
    }

    /**
     * Получить вариант изображения.
     *
     * Генерирует или возвращает существующий вариант изображения (thumbnail, medium, large).
     * Для админов поддерживает доступ к удаленным файлам.
     *
     * @param \App\Models\Media $media Медиа-файл
     * @param string $variant Имя варианта
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function serveVariant(Media $media, string $variant): RedirectResponse|BinaryFileResponse
    {
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

        // Определяем MIME-тип варианта на основе расширения
        $variantMime = $this->getMimeFromPath($variantModel->path, $media->mime);

        return $this->serveFile($media->disk, $variantModel->path, $variantMime, $this->isAdminRequest(request()));
    }

    /**
     * Отдать файл через контроллер или редирект на подписанный URL.
     *
     * Для локального диска возвращает файл напрямую через response()->file().
     * Для облачных дисков (S3) возвращает редирект на подписанный URL.
     * Использует разные TTL для публичных и админских запросов.
     *
     * @param string $diskName Имя диска
     * @param string $path Путь к файлу
     * @param string $mimeType MIME-тип файла для установки Content-Type
     * @param bool $isAdmin Является ли запрос админским
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     * @throws \InvalidArgumentException Если не удалось создать URL или файл не найден
     */
    private function serveFile(string $diskName, string $path, string $mimeType, bool $isAdmin): RedirectResponse|BinaryFileResponse
    {
        $disk = Storage::disk($diskName);

        // Для админов используем signed_ttl, для публичных - public_signed_ttl
        $ttl = $isAdmin
            ? (int) config('media.signed_ttl', 300)
            : (int) config('media.public_signed_ttl', config('media.signed_ttl', 300));

        $expiry = now('UTC')->addSeconds($ttl);

        // Для локального диска отдаём файл напрямую
        try {
            $filePath = $disk->path($path);

            if (! file_exists($filePath)) {
                throw new InvalidArgumentException('File not found on disk.');
            }

            $headers = [
                'Content-Type' => $mimeType,
                'X-URL-TTL' => (string) $ttl,
                'X-URL-Expires-At' => $expiry->toIso8601String(),
            ];

            return response()->file($filePath, $headers);
        } catch (Throwable) {
            // Если path() не поддерживается (облачные диски), используем подписанный URL
        }

        // Для облачных дисков используем подписанный URL
        try {
            $url = $disk->temporaryUrl($path, $expiry);

            return redirect()->away($url)->withHeaders([
                'X-URL-TTL' => (string) $ttl,
                'X-URL-Expires-At' => $expiry->toIso8601String(),
            ]);
        } catch (Throwable) {
            // Fallback на обычный URL для облачных дисков
            $url = $disk->url($path);

            if (! $url) {
                throw new InvalidArgumentException('Unable to generate media URL.');
            }

            return redirect()->away($url)->withHeaders([
                'X-URL-TTL' => (string) $ttl,
                'X-URL-Expires-At' => $expiry->toIso8601String(),
            ]);
        }
    }

    /**
     * Проверить, является ли запрос админским.
     *
     * Проверяет наличие аутентифицированного пользователя через guard 'api'.
     * Аутентификация выполняется через OptionalJwtAuth middleware, который
     * устанавливает пользователя в guard при наличии валидного JWT токена.
     * Это позволяет админам получать доступ к удаленным файлам даже на публичных эндпоинтах.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return bool true, если запрос от админа
     */
    private function isAdminRequest(Request $request): bool
    {
        return auth('api')->check() || auth()->check();
    }

    /**
     * Получить MIME-тип из пути файла.
     *
     * Определяет MIME-тип на основе расширения файла.
     * Если не удалось определить, возвращает оригинальный MIME-тип.
     *
     * @param string $path Путь к файлу
     * @param string $fallbackMime MIME-тип по умолчанию
     * @return string MIME-тип файла
     */
    private function getMimeFromPath(string $path, string $fallbackMime): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
        ];

        return $mimeMap[$extension] ?? $fallbackMime;
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

