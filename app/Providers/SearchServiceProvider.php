<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Search\Clients\ElasticsearchSearchClient;
use App\Domain\Search\Clients\NullSearchClient;
use App\Domain\Search\Contracts\SearchServiceInterface;
use App\Domain\Search\IndexManager;
use App\Domain\Search\SearchClientInterface;
use App\Domain\Search\SearchService;
use App\Domain\Search\Transformers\EntryToSearchDoc;
use App\Support\Errors\ErrorFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider для поисковых сервисов.
 *
 * Регистрирует SearchClientInterface, SearchServiceInterface, IndexManager и SearchService как singleton.
 * Если поиск отключён в конфиге, использует NullSearchClient.
 *
 * @package App\Providers
 */
final class SearchServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать сервисы поиска.
     *
     * Регистрирует:
     * - SearchClientInterface (ElasticsearchSearchClient или NullSearchClient)
     * - EntryToSearchDoc (singleton)
     * - IndexManager (singleton)
     * - SearchService (singleton)
     * - SearchServiceInterface → SearchService (binding)
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(SearchClientInterface::class, function ($app) {
            $config = config('search');
            $enabled = (bool) ($config['enabled'] ?? false);

            if (! $enabled) {
                return new NullSearchClient();
            }

            /** @var HttpFactory $http */
            $http = $app->make(HttpFactory::class);

            return new ElasticsearchSearchClient($http, $config['client'] ?? []);
        });

        $this->app->singleton(EntryToSearchDoc::class);

        $this->app->singleton(IndexManager::class, function ($app) {
            $config = config('search');

            return new IndexManager(
                client: $app->make(SearchClientInterface::class),
                transformer: $app->make(EntryToSearchDoc::class),
                enabled: (bool) ($config['enabled'] ?? false),
                config: $config
            );
        });

        $this->app->singleton(SearchService::class, function ($app) {
            $config = config('search');
            $indexConfig = Arr::get($config, 'indexes.entries', []);
            $readAlias = (string) Arr::get($indexConfig, 'read_alias', 'entries_read');

            return new SearchService(
                client: $app->make(SearchClientInterface::class),
                enabled: (bool) ($config['enabled'] ?? false),
                readAlias: $readAlias,
                errors: $app->make(ErrorFactory::class),
            );
        });

        // Bind interface to concrete implementation
        $this->app->bind(SearchServiceInterface::class, SearchService::class);
    }
}


