<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Resources\Admin\AdminJsonResource;
use App\Http\Resources\Admin\AdminResourceCollection;
use Illuminate\Http\Request;
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
}


