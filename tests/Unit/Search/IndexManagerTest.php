<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Domain\Search\IndexManager;
use App\Domain\Search\SearchClientInterface;
use App\Domain\Search\Transformers\EntryToSearchDoc;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

final class IndexManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_index_and_switches_aliases(): void
    {
        Config::set('search.enabled', true);

        $client = new class implements SearchClientInterface {
            public array $createdIndices = [];
            public array $deletedIndices = [];
            public array $aliasMap = [
                'entries_read' => ['entries_v1'],
                'entries_write' => ['entries_v1'],
            ];
            public array $bulkOperations = [];

            public function search(string $indexAlias, array $body): array
            {
                return [];
            }

            public function createIndex(string $indexName, array $settings, array $mappings): void
            {
                $this->createdIndices[$indexName] = [
                    'settings' => $settings,
                    'mappings' => $mappings,
                ];
            }

            public function deleteIndex(string $indexName): void
            {
                $this->deletedIndices[] = $indexName;
                $this->aliasMap = array_map(
                    static fn (array $indices): array => array_values(array_filter($indices, static fn ($index) => $index !== $indexName)),
                    $this->aliasMap
                );
            }

            public function updateAliases(array $actions): void
            {
                foreach ($actions as $action) {
                    if (isset($action['remove'])) {
                        $alias = $action['remove']['alias'];
                        $index = $action['remove']['index'];
                        $this->aliasMap[$alias] = array_values(array_filter($this->aliasMap[$alias] ?? [], static fn ($value) => $value !== $index));
                    } elseif (isset($action['add'])) {
                        $alias = $action['add']['alias'];
                        $index = $action['add']['index'];
                        $this->aliasMap[$alias][] = $index;
                    }
                }
            }

            public function getIndicesForAlias(string $alias): array
            {
                return $this->aliasMap[$alias] ?? [];
            }

            public function bulk(array $operations): void
            {
                $this->bulkOperations[] = $operations;
            }

            public function refresh(string $indexName): void
            {
                // no-op
            }
        };

        $postType = PostType::factory()->create(['slug' => 'page']);
        $taxonomy = Taxonomy::factory()->create(['slug' => 'category']);
        $term = Term::factory()->forTaxonomy($taxonomy)->create(['slug' => 'news']);

        $entries = Entry::factory()
            ->count(2)
            ->published()
            ->forPostType($postType)
            ->create([
                'data_json' => [
                    'body' => '<p>Hello ' . Str::random(4) . '</p>',
                ],
            ]);

        $entries->each(static fn (Entry $entry) => $entry->terms()->attach($term));

        $transformer = app(EntryToSearchDoc::class);
        $config = config('search');

        $manager = new IndexManager($client, $transformer, true, $config);

        $newIndex = $manager->reindex();

        self::assertArrayHasKey($newIndex, $client->createdIndices);
        self::assertNotEmpty($client->bulkOperations);
        self::assertContains($newIndex, $client->aliasMap['entries_read']);
        self::assertContains($newIndex, $client->aliasMap['entries_write']);
        self::assertContains('entries_v1', $client->deletedIndices);

        $firstBulk = Arr::first($client->bulkOperations);
        self::assertIsArray($firstBulk);
        $document = $firstBulk[1];
        self::assertArrayHasKey('id', $document);
        self::assertArrayHasKey('title', $document);
        self::assertArrayHasKey('terms', $document);
    }
}


