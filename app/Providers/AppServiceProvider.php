<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Media\Images\GdImageProcessor;
use App\Domain\Media\Images\GlideImageProcessor;
use App\Domain\Media\Images\ImageProcessor;
use App\Domain\Media\Services\CollectionRulesResolver;
use App\Domain\Media\Services\ExifManager;
use App\Domain\Media\Services\ExiftoolMediaMetadataPlugin;
use App\Domain\Media\Services\FfprobeMediaMetadataPlugin;
use App\Domain\Media\Services\MediaMetadataExtractor;
use App\Domain\Media\Services\MediaMetadataPlugin;
use App\Domain\Media\Services\MediainfoMediaMetadataPlugin;
use App\Domain\Media\Validation\CorruptionValidator;
use App\Domain\Media\Validation\MediaValidationPipeline;
use App\Domain\Media\Validation\MediaValidatorInterface;
use App\Domain\Media\Validation\MimeSignatureValidator;
use App\Domain\Media\Validation\SizeLimitValidator;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use App\Domain\Auth\JwtService;
use App\Domain\Auth\RefreshTokenRepository;
use App\Domain\Auth\RefreshTokenRepositoryImpl;
use App\Domain\Media\EloquentMediaRepository;
use App\Domain\Media\Events\MediaDeleted;
use App\Domain\Media\Events\MediaProcessed;
use App\Domain\Media\Events\MediaUploaded;
use App\Domain\Media\Listeners\LogMediaEvent;
use App\Domain\Media\Listeners\NotifyMediaEvent;
use App\Domain\Media\Listeners\PurgeCdnCache;
use App\Domain\Media\MediaRepository;
use App\Domain\Options\OptionsRepository;
use App\Domain\Sanitizer\RichTextSanitizer;
use App\Domain\View\BladeTemplateResolver;
use App\Domain\View\TemplateResolver;
use App\Models\Entry;
use App\Observers\EntryObserver;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorKernel;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Основной Service Provider приложения.
 *
 * Регистрирует основные сервисы:
 * - OptionsRepository (singleton)
 * - TemplateResolver (scoped для совместимости с Octane/Swoole)
 * - RichTextSanitizer (singleton)
 * - JwtService (singleton)
 * - RefreshTokenRepository (singleton)
 * - ErrorKernel и ErrorFactory (singleton)
 *
 * @package App\Providers
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать сервисы приложения.
     *
     * Регистрирует все основные сервисы как singleton или scoped.
     *
     * @return void
     */
    public function register(): void
    {
        // Регистрация OptionsRepository
        $this->app->singleton(OptionsRepository::class, function ($app) {
            return new OptionsRepository($app->make(CacheRepository::class));
        });

        // MediaRepository
        $this->app->singleton(MediaRepository::class, EloquentMediaRepository::class);

        // Регистрация TemplateResolver
        // Используем scoped вместо singleton для совместимости с Octane/Swoole
        $this->app->scoped(TemplateResolver::class, function () {
            return new BladeTemplateResolver(
                default: config('view_templates.default', 'entry'),
            );
        });

        // Регистрация RichTextSanitizer
        $this->app->singleton(RichTextSanitizer::class);

        // Регистрация JwtService
        $this->app->singleton(JwtService::class, function () {
            return new JwtService(config('jwt'));
        });

        // Регистрация RefreshTokenRepository
        $this->app->singleton(RefreshTokenRepository::class, RefreshTokenRepositoryImpl::class);

        // ErrorKernel — единая точка обработки ошибок API
        $this->app->singleton(ErrorKernel::class, function ($app) {
            /** @var array<string, mixed> $config */
            $config = config('errors');

            return ErrorKernel::fromConfig($config, $app);
        });

        $this->app->singleton(ErrorFactory::class, static fn ($app): ErrorFactory => $app->make(ErrorKernel::class)->factory());

        // ImageProcessor — выбор драйвера по конфигу
        $this->app->singleton(ImageProcessor::class, function () {
            $driver = (string) config('media.image.driver', 'gd');
            // Точка расширения: gd | glide | imagick | external
            switch ($driver) {
                case 'glide':
                    // Создаём Intervention ImageManager с корректным драйвером
                    $drv = (string) config('media.image.glide_driver', 'gd'); // gd|imagick
                    $driverInstance = $drv === 'imagick' ? new ImagickDriver() : new GdDriver();
                    return new GlideImageProcessor(new ImageManager(driver: $driverInstance));
                case 'gd':
                default:
                    return new GdImageProcessor();
            }
        });

        // MediaMetadataExtractor с плагинами (ffprobe/mediainfo/exiftool) и кэшированием
        $this->app->singleton(MediaMetadataExtractor::class, function ($app): MediaMetadataExtractor {
            /** @var \App\Domain\Media\Images\ImageProcessor $images */
            $images = $app->make(ImageProcessor::class);

            $plugins = [];

            // Порядок важен: пробуем плагины по порядку с graceful fallback
            if (config('media.metadata.ffprobe.enabled', true)) {
                $binary = config('media.metadata.ffprobe.binary', null);
                $plugins[] = new FfprobeMediaMetadataPlugin($binary);
            }

            if (config('media.metadata.mediainfo.enabled', false)) {
                $binary = config('media.metadata.mediainfo.binary', null);
                $plugins[] = new MediainfoMediaMetadataPlugin($binary);
            }

            if (config('media.metadata.exiftool.enabled', false)) {
                $binary = config('media.metadata.exiftool.binary', null);
                $plugins[] = new ExiftoolMediaMetadataPlugin($binary);
            }

            /** @var iterable<MediaMetadataPlugin> $pluginsIterable */
            $pluginsIterable = $plugins;

            $cache = $app->make(CacheRepository::class);
            $cacheTtl = (int) config('media.metadata.cache_ttl', 3600);

            return new MediaMetadataExtractor($images, $pluginsIterable, $cache, $cacheTtl);
        });

        // ExifManager для управления EXIF данными
        $this->app->singleton(ExifManager::class, function ($app): ExifManager {
            /** @var \App\Domain\Media\Images\ImageProcessor $images */
            $images = $app->make(ImageProcessor::class);

            return new ExifManager($images);
        });

        // CollectionRulesResolver для получения правил коллекций
        $this->app->singleton(CollectionRulesResolver::class);

        // MediaValidationPipeline с валидаторами
        $this->app->singleton(MediaValidationPipeline::class, function ($app): MediaValidationPipeline {
            /** @var \App\Domain\Media\Images\ImageProcessor $images */
            $images = $app->make(ImageProcessor::class);

            $validators = [
                new MimeSignatureValidator(),
                new CorruptionValidator($images),
            ];

            /** @var iterable<MediaValidatorInterface> $validatorsIterable */
            $validatorsIterable = $validators;

            return new MediaValidationPipeline($validatorsIterable);
        });
    }

    /**
     * Загрузить сервисы приложения.
     *
     * Регистрирует EntryObserver для модели Entry.
     * Регистрирует слушателей событий медиа-файлов.
     * Создаёт директорию для кэша HTMLPurifier.
     * Устанавливает JWT leeway для учёта расхождения часов.
     *
     * @return void
     */
    public function boot(): void
    {
        Entry::observe(EntryObserver::class);

        // Регистрация слушателей событий медиа-файлов
        Event::listen(MediaUploaded::class, [LogMediaEvent::class, 'handleMediaUploaded']);
        Event::listen(MediaUploaded::class, [NotifyMediaEvent::class, 'handleMediaUploaded']);
        Event::listen(MediaUploaded::class, [PurgeCdnCache::class, 'handleMediaUploaded']);

        Event::listen(MediaProcessed::class, [LogMediaEvent::class, 'handleMediaProcessed']);
        Event::listen(MediaProcessed::class, [NotifyMediaEvent::class, 'handleMediaProcessed']);
        Event::listen(MediaProcessed::class, [PurgeCdnCache::class, 'handleMediaProcessed']);

        Event::listen(MediaDeleted::class, [LogMediaEvent::class, 'handleMediaDeleted']);
        Event::listen(MediaDeleted::class, [NotifyMediaEvent::class, 'handleMediaDeleted']);
        Event::listen(MediaDeleted::class, [PurgeCdnCache::class, 'handleMediaDeleted']);
        
        // Создаем директорию для кэша HTMLPurifier (idempotent)
        app('files')->ensureDirectoryExists(storage_path('app/purifier'));

        // Set JWT leeway to account for clock drift between server and client
        // This ensures stable token verification when there are small time differences
        \Firebase\JWT\JWT::$leeway = (int) config('jwt.leeway', 5); // Default: 5 seconds
    }
}
