<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Resources\Admin\AdminJsonResource;
use App\Http\Resources\Admin\AdminResourceCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class AdminResourceResponseTest extends TestCase
{
    public function test_admin_json_resource_applies_default_headers(): void
    {
        $resource = new class(null) extends AdminJsonResource {
            public function toArray($request): array
            {
                return ['ok' => true];
            }
        };

        $response = $resource->toResponse(new Request());

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('Cookie', $response->headers->get('Vary'));
    }

    public function test_child_resource_can_extend_response_configuration(): void
    {
        $resource = new class(null) extends AdminJsonResource {
            protected function prepareAdminResponse($request, Response $response): void
            {
                $response->setStatusCode(Response::HTTP_CREATED);
                parent::prepareAdminResponse($request, $response);
            }

            public function toArray($request): array
            {
                return ['ok' => true];
            }
        };

        $response = $resource->toResponse(new Request());

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('Cookie', $response->headers->get('Vary'));
    }

    public function test_admin_resource_collection_applies_default_headers(): void
    {
        $collection = new class(new Collection([['id' => 1]])) extends AdminResourceCollection {
            public function toArray($request): array
            {
                return [
                    'data' => $this->collection,
                ];
            }
        };

        $response = $collection->toResponse(new Request());

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('Cookie', $response->headers->get('Vary'));
    }

    public function test_build_pagination_merges_links_and_meta(): void
    {
        $paginator = new LengthAwarePaginator(
            items: collect([['id' => 1], ['id' => 2]]),
            total: 10,
            perPage: 2,
            currentPage: 2,
            options: ['path' => 'https://example.test/options']
        );

        $collection = new class($paginator) extends AdminResourceCollection {
            public function toArray($request): array
            {
                return [
                    'data' => $this->collection,
                ];
            }

            public function paginationInformation($request, $paginated, $default): array
            {
                return $this->buildPagination($default);
            }
        };

        $result = $collection->paginationInformation(new Request(), [], [
            'links' => ['existing' => 'preset'],
            'meta' => ['seed' => 'value'],
        ]);

        $this->assertSame('https://example.test/options?page=1', $result['links']['first']);
        $this->assertSame('https://example.test/options?page=5', $result['links']['last']);
        $this->assertSame('https://example.test/options?page=1', $result['links']['prev']);
        $this->assertSame('https://example.test/options?page=3', $result['links']['next']);
        $this->assertSame('preset', $result['links']['existing']);

        $this->assertSame('value', $result['meta']['seed']);
        $this->assertSame(2, $result['meta']['current_page']);
        $this->assertSame(3, $result['meta']['from']);
        $this->assertSame(4, $result['meta']['to']);
        $this->assertSame(5, $result['meta']['last_page']);
        $this->assertSame(10, $result['meta']['total']);
        $this->assertSame(2, $result['meta']['per_page']);
        $this->assertSame('https://example.test/options', $result['meta']['path']);
    }
}


