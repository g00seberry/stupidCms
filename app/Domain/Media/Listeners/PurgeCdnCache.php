<?php

declare(strict_types=1);

namespace App\Domain\Media\Listeners;

use App\Domain\Media\Events\MediaDeleted;
use App\Domain\Media\Events\MediaProcessed;
use App\Domain\Media\Events\MediaUploaded;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Слушатель для очистки кэша CDN при событиях медиа-файлов.
 *
 * Очищает кэш CDN при загрузке, обработке и удалении медиа-файлов.
 * Поддерживает различные CDN провайдеры через конфигурацию.
 *
 * @package App\Domain\Media\Listeners
 */
final class PurgeCdnCache
{
    /**
     * Обработать событие загрузки медиа-файла.
     *
     * Очищает кэш CDN для загруженного файла и его будущих вариантов.
     *
     * @param \App\Domain\Media\Events\MediaUploaded $event Событие загрузки
     * @return void
     */
    public function handleMediaUploaded(MediaUploaded $event): void
    {
        $media = $event->media;

        if (! $this->isCdnEnabled($media->disk)) {
            return;
        }

        $urls = $this->buildMediaUrls($media);

        $this->purgeUrls($urls, 'uploaded', [
            'media_id' => $media->id,
        ]);
    }

    /**
     * Обработать событие обработки медиа-файла.
     *
     * Очищает кэш CDN для сгенерированного варианта.
     *
     * @param \App\Domain\Media\Events\MediaProcessed $event Событие обработки
     * @return void
     */
    public function handleMediaProcessed(MediaProcessed $event): void
    {
        $media = $event->media;
        $variant = $event->variant;

        if (! $this->isCdnEnabled($media->disk)) {
            return;
        }

        $urls = $this->buildVariantUrls($media, $variant);

        $this->purgeUrls($urls, 'processed', [
            'media_id' => $media->id,
            'variant' => $variant->variant,
        ]);
    }

    /**
     * Обработать событие удаления медиа-файла.
     *
     * Очищает кэш CDN для удалённого файла и всех его вариантов.
     *
     * @param \App\Domain\Media\Events\MediaDeleted $event Событие удаления
     * @return void
     */
    public function handleMediaDeleted(MediaDeleted $event): void
    {
        $media = $event->media;

        if (! $this->isCdnEnabled($media->disk)) {
            return;
        }

        $urls = $this->buildMediaUrls($media);

        // Добавляем URL всех вариантов
        foreach ($media->variants as $variant) {
            $urls = array_merge($urls, $this->buildVariantUrls($media, $variant));
        }

        $this->purgeUrls($urls, 'deleted', [
            'media_id' => $media->id,
        ]);
    }

    /**
     * Проверить, включён ли CDN для диска.
     *
     * @param string $disk Имя диска
     * @return bool
     */
    private function isCdnEnabled(string $disk): bool
    {
        return config("media.cdn.enabled", false) === true
            && config("media.cdn.disk") === $disk;
    }

    /**
     * Построить URL медиа-файла для CDN.
     *
     * @param \App\Models\Media $media Медиа-файл
     * @return list<string> Список URL для очистки
     */
    private function buildMediaUrls(\App\Models\Media $media): array
    {
        $disk = Storage::disk($media->disk);
        $url = $disk->url($media->path);

        return [$url];
    }

    /**
     * Построить URL варианта медиа-файла для CDN.
     *
     * @param \App\Models\Media $media Медиа-файл
     * @param \App\Models\MediaVariant $variant Вариант
     * @return list<string> Список URL для очистки
     */
    private function buildVariantUrls(\App\Models\Media $media, \App\Models\MediaVariant $variant): array
    {
        $disk = Storage::disk($media->disk);
        $url = $disk->url($variant->path);

        return [$url];
    }

    /**
     * Очистить кэш CDN для указанных URL.
     *
     * @param list<string> $urls Список URL для очистки
     * @param string $eventType Тип события (uploaded, processed, deleted)
     * @param array<string, mixed> $context Контекст для логирования
     * @return void
     */
    private function purgeUrls(array $urls, string $eventType, array $context): void
    {
        $provider = config('media.cdn.provider', 'cloudflare');

        foreach ($urls as $url) {
            try {
                $this->purgeUrl($url, $provider);
            } catch (\Throwable $e) {
                Log::warning('Failed to purge CDN cache', [
                    'url' => $url,
                    'provider' => $provider,
                    'event_type' => $eventType,
                    'error' => $e->getMessage(),
                    ...$context,
                ]);
            }
        }

        Log::info('CDN cache purged', [
            'urls_count' => count($urls),
            'provider' => $provider,
            'event_type' => $eventType,
            ...$context,
        ]);
    }

    /**
     * Очистить кэш CDN для одного URL.
     *
     * @param string $url URL для очистки
     * @param string $provider Провайдер CDN (cloudflare, fastly, cloudfront и т.д.)
     * @return void
     * @throws \RuntimeException Если провайдер не поддерживается
     */
    private function purgeUrl(string $url, string $provider): void
    {
        match ($provider) {
            'cloudflare' => $this->purgeCloudflare($url),
            'fastly' => $this->purgeFastly($url),
            'cloudfront' => $this->purgeCloudfront($url),
            default => throw new \RuntimeException("Unsupported CDN provider: {$provider}"),
        };
    }

    /**
     * Очистить кэш Cloudflare.
     *
     * @param string $url URL для очистки
     * @return void
     */
    private function purgeCloudflare(string $url): void
    {
        $zoneId = config('media.cdn.cloudflare.zone_id');
        $apiToken = config('media.cdn.cloudflare.api_token');

        if (! $zoneId || ! $apiToken) {
            Log::warning('Cloudflare CDN purge skipped: missing configuration');
            return;
        }

        // TODO: Реализовать реальный API-вызов к Cloudflare
        // Пример: HTTP POST к https://api.cloudflare.com/client/v4/zones/{zone_id}/purge_cache
        Log::debug('Cloudflare cache purge requested', ['url' => $url]);
    }

    /**
     * Очистить кэш Fastly.
     *
     * @param string $url URL для очистки
     * @return void
     */
    private function purgeFastly(string $url): void
    {
        $serviceId = config('media.cdn.fastly.service_id');
        $apiToken = config('media.cdn.fastly.api_token');

        if (! $serviceId || ! $apiToken) {
            Log::warning('Fastly CDN purge skipped: missing configuration');
            return;
        }

        // TODO: Реализовать реальный API-вызов к Fastly
        // Пример: HTTP POST к https://api.fastly.com/service/{service_id}/purge/{url}
        Log::debug('Fastly cache purge requested', ['url' => $url]);
    }

    /**
     * Очистить кэш CloudFront.
     *
     * @param string $url URL для очистки
     * @return void
     */
    private function purgeCloudfront(string $url): void
    {
        $distributionId = config('media.cdn.cloudfront.distribution_id');
        $accessKey = config('media.cdn.cloudfront.access_key');
        $secretKey = config('media.cdn.cloudfront.secret_key');

        if (! $distributionId || ! $accessKey || ! $secretKey) {
            Log::warning('CloudFront CDN purge skipped: missing configuration');
            return;
        }

        // TODO: Реализовать реальный API-вызов к CloudFront через AWS SDK
        // Пример: CreateInvalidation через CloudFrontClient
        Log::debug('CloudFront cache purge requested', ['url' => $url]);
    }
}

