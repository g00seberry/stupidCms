<?php

declare(strict_types=1);

namespace App\Domain\Search\Clients;

use App\Domain\Search\SearchClientInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;

/**
 * Реализация SearchClientInterface для Elasticsearch.
 *
 * Использует HTTP клиент Laravel для взаимодействия с Elasticsearch API.
 * Поддерживает базовую аутентификацию и настройку SSL.
 *
 * @package App\Domain\Search\Clients
 */
final class ElasticsearchSearchClient implements SearchClientInterface
{
    /**
     * @param \Illuminate\Http\Client\Factory $http HTTP клиент
     * @param array<string, mixed> $config Конфигурация (hosts, timeout, username, password, verify_ssl)
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config
    ) {
    }

    /**
     * Выполнить поисковый запрос.
     *
     * @param string $indexAlias Алиас индекса для поиска
     * @param array<string, mixed> $body Тело запроса (Elasticsearch query DSL)
     * @return array<string, mixed> Ответ Elasticsearch
     */
    public function search(string $indexAlias, array $body): array
    {
        $response = $this->request()
            ->post(sprintf('/%s/_search', trim($indexAlias, '/')), $body);

        return $response->throw()->json();
    }

    /**
     * Создать индекс с указанными настройками и маппингами.
     *
     * @param string $indexName Имя индекса
     * @param array<string, mixed> $settings Настройки индекса
     * @param array<string, mixed> $mappings Маппинги полей
     * @return void
     */
    public function createIndex(string $indexName, array $settings, array $mappings): void
    {
        $payload = array_filter([
            'settings' => $settings,
            'mappings' => $mappings,
        ]);

        $this->request()
            ->put(sprintf('/%s', trim($indexName, '/')), $payload)
            ->throw();
    }

    /**
     * Удалить индекс.
     *
     * Игнорирует ошибку 404 (индекс уже не существует).
     *
     * @param string $indexName Имя индекса для удаления
     * @return void
     */
    public function deleteIndex(string $indexName): void
    {
        $response = $this->request()
            ->delete(sprintf('/%s', trim($indexName, '/')));

        if ($response->status() === 404) {
            return;
        }

        $response->throw();
    }

    /**
     * Обновить алиасы индексов.
     *
     * @param array<int, array<string, mixed>> $actions Массив действий (add, remove)
     * @return void
     */
    public function updateAliases(array $actions): void
    {
        $this->request()
            ->post('/_aliases', ['actions' => $actions])
            ->throw();
    }

    /**
     * Возвращает список индексов, привязанных к алиасу.
     *
     * @param string $alias Алиас индекса
     * @return list<string> Список имён индексов
     */
    public function getIndicesForAlias(string $alias): array
    {
        $response = $this->request()
            ->get(sprintf('/_alias/%s', trim($alias, '/')));

        if ($response->status() === 404) {
            return [];
        }

        $data = $response->throw()->json();

        return array_keys($data ?? []);
    }

    /**
     * Выполнить bulk-операции.
     *
     * Отправляет операции в формате NDJSON (newline-delimited JSON).
     * Проверяет наличие ошибок в ответе.
     *
     * @param list<array<string, mixed>> $operations Массив операций
     * @return void
     * @throws \RuntimeException Если bulk-операции завершились с ошибками
     */
    public function bulk(array $operations): void
    {
        if ($operations === []) {
            return;
        }

        $payload = $this->toNdjson($operations);

        $response = $this->request()
            ->withHeaders(['Content-Type' => 'application/x-ndjson'])
            ->post('/_bulk', $payload);

        $response->throw();

        $body = $response->json();

        if (is_array($body) && Arr::get($body, 'errors') === true) {
            throw new \RuntimeException('Bulk indexing completed with errors.');
        }
    }

    /**
     * Обновить индекс (сделать изменения видимыми для поиска).
     *
     * @param string $indexName Имя индекса
     * @return void
     */
    public function refresh(string $indexName): void
    {
        $this->request()
            ->post(sprintf('/%s/_refresh', trim($indexName, '/')))
            ->throw();
    }

    /**
     * Преобразовать массив операций в NDJSON формат.
     *
     * @param list<array<string, mixed>> $operations Массив операций
     * @return string NDJSON строка
     */
    private function toNdjson(array $operations): string
    {
        $lines = array_map(
            static fn (array $operation): string => json_encode($operation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $operations
        );

        return implode("\n", $lines) . "\n";
    }

    /**
     * Создать настроенный HTTP запрос к Elasticsearch.
     *
     * Настраивает базовый URL, timeout, SSL верификацию и базовую аутентификацию.
     *
     * @return \Illuminate\Http\Client\PendingRequest Настроенный запрос
     */
    private function request(): PendingRequest
    {
        $hosts = $this->config['hosts'] ?? ['http://127.0.0.1:9200'];
        $host = is_array($hosts) && $hosts !== [] ? $hosts[0] : 'http://127.0.0.1:9200';

        $request = $this->http->baseUrl(rtrim($host, '/'))
            ->timeout((float) ($this->config['timeout'] ?? 2.5));

        if (($this->config['verify_ssl'] ?? true) === false) {
            $request->withoutVerifying();
        }

        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;

        if ($username !== null && $username !== '') {
            $request->withBasicAuth($username, $password ?? '');
        }

        return $request;
    }
}


