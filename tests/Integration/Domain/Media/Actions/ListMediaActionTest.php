<?php

declare(strict_types=1);

namespace Tests\Integration\Domain\Media\Actions;

use App\Domain\Media\Actions\ListMediaAction;
use App\Domain\Media\MediaQuery;
use App\Domain\Media\MediaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LaravelLengthAwarePaginator;
use Mockery;
use Tests\Support\IntegrationTestCase;

final class ListMediaActionTest extends IntegrationTestCase
{
    private MediaRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(MediaRepository::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createAction(): ListMediaAction
    {
        return new ListMediaAction($this->repository);
    }

    public function test_paginates_media_correctly(): void
    {
        $query = new MediaQuery(
            search: null,
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: \App\Domain\Media\MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 15
        );

        $paginator = new LaravelLengthAwarePaginator(
            [],
            0,
            15,
            1
        );

        $this->repository->shouldReceive('paginate')
            ->once()
            ->with($query)
            ->andReturn($paginator);

        $action = $this->createAction();
        $result = $action->execute($query);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(0, $result->total());
    }

    public function test_delegates_to_repository(): void
    {
        $query = new MediaQuery(
            search: 'test',
            kind: 'image',
            mimePrefix: 'image/jpeg',
            collection: 'banners',
            deletedFilter: \App\Domain\Media\MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'size_bytes',
            order: 'asc',
            page: 2,
            perPage: 20
        );

        $paginator = new LaravelLengthAwarePaginator(
            [],
            0,
            20,
            2
        );

        $this->repository->shouldReceive('paginate')
            ->once()
            ->with(Mockery::type(MediaQuery::class))
            ->andReturn($paginator);

        $action = $this->createAction();
        $result = $action->execute($query);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    public function test_handles_empty_results(): void
    {
        $query = new MediaQuery(
            search: null,
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: \App\Domain\Media\MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 15
        );

        $paginator = new LaravelLengthAwarePaginator(
            [],
            0,
            15,
            1
        );

        $this->repository->shouldReceive('paginate')
            ->once()
            ->with($query)
            ->andReturn($paginator);

        $action = $this->createAction();
        $result = $action->execute($query);

        $this->assertSame(0, $result->total());
        $this->assertSame(0, $result->count());
        $this->assertTrue($result->isEmpty());
    }
}


