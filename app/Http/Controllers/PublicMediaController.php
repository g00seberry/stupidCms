<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Media;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Контроллер для публичного доступа к медиа-файлам.
 *
 * Предоставляет подписанные URL для доступа к медиа-файлам без аутентификации.
 * Использует TTL из конфигурации для ограничения времени жизни подписанных URL.
 * Поддерживает доступ к оригинальным файлам.
 *
 * @package App\Http\Controllers
 */
class PublicMediaController extends Controller
{
    use ThrowsErrors;

    /**
     * Получить публичный доступ к медиа-файлу.
     *
     * Генерирует подписанный URL с ограниченным временем жизни (TTL из config).
     * Для локальных дисков возвращает файл напрямую, для облачных - редирект на подписанный URL.
     * Не требует аутентификации, но проверяет существование медиа-файла.
     *
     * @group Media
     * @name Get public media
     * @unauthenticated
     * @urlParam id string required ULID идентификатор медиа-файла. Example: 01HZYQNGQK74ZP6YVZ6E7SFJ2D
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
     *   "trace_id": "00-0c0c0c0c0c0c0c0c0c0c0c0c0c0c0c01-0c0c0c0c0c0c0c0c-01"
     * }
     * @response status=500 {
     *   "type": "https://stupidcms.dev/problems/media-download-error",
     *   "title": "Failed to generate media URL",
     *   "status": 500,
     *   "code": "MEDIA_DOWNLOAD_ERROR",
     *   "detail": "Failed to generate media URL.",
     *   "meta": {
     *     "request_id": "0c0c0c0c-0c0c-0c0c-0c0c-0c0c0c0c0c02",
     *     "media_id": "01HZYQNGQK74ZP6YVZ6E7SFJ2D"
     *   },
     *   "trace_id": "00-0c0c0c0c0c0c0c0c0c0c0c0c0c0c0c02-0c0c0c0c0c0c0c0c-01"
     * }
     *
     * @param string $id ULID идентификатор медиа-файла
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function show(string $id): RedirectResponse|BinaryFileResponse
    {
        $media = Media::query()->find($id);

        if (! $media) {
            $this->throwMediaNotFound($id);
        }

        // Проверяем, что медиа не удалено (soft delete)
        if ($media->trashed()) {
            $this->throwMediaNotFound($id);
        }

        try {
            return $this->serveFile($media->disk, $media->path, $media->mime);
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
     * Отдать файл через контроллер или редирект на подписанный URL.
     *
     * Для локального диска возвращает файл напрямую через response()->file().
     * Для облачных дисков (S3) возвращает редирект на подписанный URL.
     * Использует TTL из конфигурации media.public_signed_ttl (или media.signed_ttl как fallback).
     *
     * @param string $diskName Имя диска
     * @param string $path Путь к файлу
     * @param string $mimeType MIME-тип файла для установки Content-Type
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     * @throws \InvalidArgumentException Если не удалось создать URL или файл не найден
     */
    private function serveFile(string $diskName, string $path, string $mimeType): RedirectResponse|BinaryFileResponse
    {
        $disk = Storage::disk($diskName);
        $ttl = (int) config('media.public_signed_ttl', config('media.signed_ttl', 300));
        $expiry = now('UTC')->addSeconds($ttl);

        // Для локального диска отдаём файл напрямую
        // Проверяем, поддерживает ли диск метод path() (только для локальных дисков)
        try {
            $filePath = $disk->path($path);

            if (! file_exists($filePath)) {
                throw new InvalidArgumentException('File not found on disk.');
            }

            // Для локальных дисков TTL не применим, но добавляем заголовки для консистентности
            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'X-URL-TTL' => (string) $ttl,
                'X-URL-Expires-At' => $expiry->toIso8601String(),
            ]);
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

