<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Media\Services\OnDemandVariantService;
use App\Models\Media;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Контроллер для публичного доступа к медиа-файлам.
 *
 * Предоставляет подписанные URL для доступа к медиа-файлам без аутентификации.
 * Использует TTL из конфигурации для ограничения времени жизни подписанных URL.
 * Поддерживает доступ к оригинальным файлам и вариантам изображений (thumbnails, resized).
 *
 * @package App\Http\Controllers
 */
class PublicMediaController extends Controller
{
    use ThrowsErrors;

    /**
     * @param \App\Domain\Media\Services\OnDemandVariantService $variantService Сервис для генерации вариантов
     */
    public function __construct(
        private readonly OnDemandVariantService $variantService
    ) {
    }

    /**
     * Получить публичный доступ к медиа-файлу.
     *
     * Генерирует подписанный URL с ограниченным временем жизни (TTL из config).
     * Для локальных дисков возвращает файл напрямую, для облачных - редирект на подписанный URL.
     * Не требует аутентификации, но проверяет существование медиа-файла.
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
     * Получить публичный доступ к варианту изображения.
     *
     * Генерирует вариант изображения (thumbnail, medium, large и т.д.) по требованию
     * и возвращает подписанный URL с ограниченным временем жизни (TTL из config).
     * Для локальных дисков возвращает файл напрямую, для облачных - редирект на подписанный URL.
     * Не требует аутентификации, но проверяет существование медиа-файла.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param string $id ULID идентификатор медиа-файла
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function preview(Request $request, string $id): RedirectResponse|BinaryFileResponse
    {
        $variant = $request->query('variant', 'thumbnail');

        $media = Media::query()->find($id);

        if (! $media) {
            $this->throwMediaNotFound($id);
        }

        // Проверяем, что медиа не удалено (soft delete)
        if ($media->trashed()) {
            $this->throwMediaNotFound($id);
        }

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

        try {
            return $this->serveFile($media->disk, $variantModel->path, $media->mime);
        } catch (Throwable $exception) {
            report($exception);

            $this->throwError(
                ErrorCode::MEDIA_DOWNLOAD_ERROR,
                'Failed to generate media variant URL.',
                [
                    'media_id' => $id,
                    'variant' => $variant,
                ],
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

            return response()->file($filePath, ['Content-Type' => $mimeType]);
        } catch (Throwable) {
            // Если path() не поддерживается (облачные диски), используем подписанный URL
        }

        // Для облачных дисков используем подписанный URL
        try {
            $url = $disk->temporaryUrl($path, $expiry);

            return redirect()->away($url);
        } catch (Throwable) {
            // Fallback на обычный URL для облачных дисков
            $url = $disk->url($path);

            if (! $url) {
                throw new InvalidArgumentException('Unable to generate media URL.');
            }

            return redirect()->away($url);
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

