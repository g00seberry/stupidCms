<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Media\Images\GdImageProcessor;
use App\Domain\Media\Images\GlideImageProcessor;
use App\Domain\Media\Images\ImageProcessor;
use App\Domain\Media\Services\ExifManager;
use App\Domain\Media\Services\GetId3MediaMetadataPlugin;
use App\Domain\Media\Services\ExiftoolMediaMetadataPlugin;
use App\Domain\Media\Services\FfprobeMediaMetadataPlugin;
use App\Domain\Media\Services\MediaMetadataExtractor;
use App\Domain\Media\Services\MediaMetadataPlugin;
use App\Domain\Media\Services\MediainfoMediaMetadataPlugin;
use App\Domain\Media\Validation\CorruptionValidator;
use App\Domain\Media\Validation\MediaConfigValidator;
use App\Domain\Media\Validation\MediaValidationPipeline;
use App\Domain\Media\Validation\MediaValidatorInterface;
use App\Domain\Media\Validation\MimeSignatureValidator;
use App\Domain\Media\Validation\SizeLimitValidator;
use App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface;
use App\Domain\Blueprint\Validation\EntryValidationServiceInterface;
use App\Domain\Blueprint\Validation\PathValidationRulesConverter;
use App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface;
use App\Domain\Blueprint\Validation\Rules\Handlers\DistinctRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\ConditionalRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\FieldComparisonRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\MaxRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\MinRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\NullableRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\PatternRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\MediaMimeRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\RefPostTypeRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\RequiredRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerRegistry;
use App\Domain\Blueprint\Validation\Rules\Handlers\TypeRuleHandler;
use App\Http\Requests\Admin\Path\Constraints\ConstraintsValidationBuilderRegistry;
use App\Http\Requests\Admin\Path\Constraints\MediaConstraintsValidationBuilder;
use App\Http\Requests\Admin\Path\Constraints\RefConstraintsValidationBuilder;
use App\Services\Path\Constraints\MediaPathConstraintsBuilder;
use App\Services\Path\Constraints\PathConstraintsBuilderRegistry;
use App\Services\Path\Constraints\RefPathConstraintsBuilder;
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
use App\Events\Blueprint\BlueprintStructureChanged;
use App\Listeners\Blueprint\RematerializeEmbeds;
use App\Domain\Media\MediaRepository;
use App\Domain\Sanitizer\RichTextSanitizer;
use App\Domain\View\BladeTemplateResolver;
use App\Domain\View\TemplatePathValidator;
use App\Domain\View\TemplateResolver;
use App\Models\Entry;
use App\Models\RouteNode;
use App\Observers\EntryObserver;
use App\Observers\RouteNodeObserver;
use App\Services\Blueprint\BlueprintDependencyGraphLoader;
use App\Services\Blueprint\BlueprintDependencyGraphLoaderInterface;
use App\Services\Blueprint\BlueprintStructureService;
use App\Services\Blueprint\CyclicDependencyValidator;
use App\Services\Blueprint\DependencyGraphService;
use App\Services\Blueprint\MaterializationService;
use App\Services\Blueprint\PathConflictValidator;
use App\Services\Blueprint\PathMaterializer;
use App\Services\Blueprint\PathMaterializerInterface;
use App\Services\Entry\EntryIndexer;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorKernel;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Основной Service Provider приложения.
 *
 * Регистрирует основные сервисы:
 * - TemplatePathValidator (singleton)
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
        // MediaRepository
        $this->app->singleton(MediaRepository::class, EloquentMediaRepository::class);

        // Регистрация TemplatePathValidator
        $this->app->singleton(TemplatePathValidator::class);

        // Регистрация TemplateResolver
        // Используем scoped вместо singleton для совместимости с Octane/Swoole
        $this->app->scoped(TemplateResolver::class, function ($app) {
            return new BladeTemplateResolver(
                validator: $app->make(TemplatePathValidator::class),
                default: config('view_templates.default', 'templates.index'),
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
            // getID3 - чистая PHP библиотека, не требует внешних утилит, пробуем первой
            if (config('media.metadata.getid3.enabled', true)) {
                $plugins[] = new GetId3MediaMetadataPlugin();
            }

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

        // Blueprint services
        $this->app->singleton(DependencyGraphService::class);
        $this->app->singleton(CyclicDependencyValidator::class);
        $this->app->singleton(PathConflictValidator::class);

        // Blueprint materialization services
        $this->app->bind(BlueprintDependencyGraphLoaderInterface::class, BlueprintDependencyGraphLoader::class);
        $this->app->bind(PathMaterializerInterface::class, function ($app) {
            $batchInsertSize = (int) config('blueprint.batch_insert_size', 500);
            return new PathMaterializer(
                $batchInsertSize,
                $app->make(PathConstraintsBuilderRegistry::class)
            );
        });

        $this->app->singleton(MaterializationService::class, function ($app) {
            $maxEmbedDepth = (int) config('blueprint.max_embed_depth', 5);
            return new MaterializationService(
                $app->make(PathConflictValidator::class),
                $app->make(BlueprintDependencyGraphLoaderInterface::class),
                $app->make(PathMaterializerInterface::class),
                $maxEmbedDepth
            );
        });

        $this->app->singleton(BlueprintStructureService::class);
        
        // Rule factory
        $this->app->bind(
            \App\Domain\Blueprint\Validation\Rules\RuleFactory::class,
            \App\Domain\Blueprint\Validation\Rules\RuleFactoryImpl::class
        );
        
        // Path validation rules converter
        $this->app->bind(
            PathValidationRulesConverterInterface::class,
            PathValidationRulesConverter::class
        );
        
        // Path constraints validation builders registry
        $this->app->singleton(ConstraintsValidationBuilderRegistry::class, function () {
            $registry = new ConstraintsValidationBuilderRegistry();

            // Регистрируем билдеры для различных типов данных
            $registry->register('ref', new RefConstraintsValidationBuilder());
            $registry->register('media', new MediaConstraintsValidationBuilder());

            return $registry;
        });

        // Path constraints builders registry (для работы с constraints: сериализация, синхронизация, валидация, копирование)
        $this->app->singleton(PathConstraintsBuilderRegistry::class, function () {
            $registry = new PathConstraintsBuilderRegistry();

            // Регистрируем билдеры для различных типов данных
            $registry->register('ref', new RefPathConstraintsBuilder());
            $registry->register('media', new MediaPathConstraintsBuilder());

            return $registry;
        });
        
        // Entry validation service (использует PathConstraintsBuilderRegistry)
        $this->app->singleton(
            \App\Domain\Blueprint\Validation\EntryValidationServiceInterface::class,
            \App\Domain\Blueprint\Validation\EntryValidationService::class
        );
        
        // Laravel validation adapter с registry handlers
        $this->app->singleton(RuleHandlerRegistry::class, function () {
            $registry = new RuleHandlerRegistry();

            // Регистрируем все handlers
            $registry->register('required', new RequiredRuleHandler());
            $registry->register('nullable', new NullableRuleHandler());
            $registry->register('min', new MinRuleHandler());
            $registry->register('max', new MaxRuleHandler());
            $registry->register('pattern', new PatternRuleHandler());
            $registry->register('distinct', new DistinctRuleHandler());
            $registry->register('required_if', new ConditionalRuleHandler());
            $registry->register('prohibited_unless', new ConditionalRuleHandler());
            $registry->register('required_unless', new ConditionalRuleHandler());
            $registry->register('prohibited_if', new ConditionalRuleHandler());
            $registry->register('field_comparison', new FieldComparisonRuleHandler());
            $registry->register('type', new TypeRuleHandler());
            $registry->register('ref_post_type', new RefPostTypeRuleHandler());
            $registry->register('media_mime', new MediaMimeRuleHandler());

            return $registry;
        });

        $this->app->singleton(
            \App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface::class,
            \App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapter::class
        );

        // Entry indexing service
        $this->app->singleton(EntryIndexer::class);

        // Entry related data services
        $this->app->singleton(\App\Services\Entry\RelatedDataProviderRegistry::class, function () {
            $registry = new \App\Services\Entry\RelatedDataProviderRegistry();
            
            // Регистрируем провайдер для Entry данных
            $registry->register(new \App\Services\Entry\Providers\EntryRelatedDataProvider());
            
            // Регистрируем провайдер для Media данных
            $registry->register(new \App\Services\Entry\Providers\MediaRelatedDataProvider());
            
            return $registry;
        });

        $this->app->singleton(\App\Services\Entry\EntryRefExtractor::class);
        $this->app->singleton(\App\Services\Entry\EntryMediaExtractor::class);
        $this->app->singleton(\App\Services\Entry\EntryRelatedDataLoader::class);
        $this->app->singleton(\App\Services\Entry\EntryRelatedDataFormatter::class);
    }

    /**
     * Загрузить сервисы приложения.
     *
     * Регистрирует EntryObserver для модели Entry.
     * Регистрирует namespace для шаблонов в папке templates.
     * Регистрирует слушателей событий медиа-файлов.
     * Валидирует конфигурацию медиа-файлов.
     * Создаёт директорию для кэша HTMLPurifier.
     * Устанавливает JWT leeway для учёта расхождения часов.
     *
     * @return void
     */
    public function boot(): void
    {
        // Регистрация namespace для шаблонов
        // Шаблоны должны находиться только в resources/views/templates
        View::addNamespace('templates', resource_path('views/templates'));

        Entry::observe(EntryObserver::class);
        RouteNode::observe(RouteNodeObserver::class);

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

        // Регистрация слушателей событий blueprint
        Event::listen(BlueprintStructureChanged::class, RematerializeEmbeds::class);

        // Валидация конфигурации медиа-файлов
        (new MediaConfigValidator())->validate();
        
        // Создаем директорию для кэша HTMLPurifier (idempotent)
        app('files')->ensureDirectoryExists(storage_path('app/purifier'));

        // Set JWT leeway to account for clock drift between server and client
        // This ensures stable token verification when there are small time differences
        \Firebase\JWT\JWT::$leeway = (int) config('jwt.leeway', 5); // Default: 5 seconds
    }
}
