<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\HttpErrorException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Support\FeatureTestCase;

final class InfrastructureProblemTest extends FeatureTestCase
{
    public function test_query_exception_returns_neutral_detail(): void
    {
        Route::middleware('api')->get('/test/query-exception', function (): void {
            throw new QueryException(
                config('database.default'),
                'select 1',
                [],
                new RuntimeException('Database is unavailable')
            );
        });

        $response = $this->getJson('/test/query-exception');

        $response->assertStatus(500);
        $response->assertJsonFragment([
            'type' => $this->typeUri(ErrorCode::INTERNAL_SERVER_ERROR),
            'detail' => $this->defaultDetail(ErrorCode::INTERNAL_SERVER_ERROR),
        ]);
    }

    public function test_query_exception_uses_problem_exception_detail_when_available(): void
    {
        Route::middleware('api')->get('/test/query-exception-problem', function (): void {
            /** @var ErrorFactory $factory */
            $factory = app(ErrorFactory::class);

            $payload = $factory->for(ErrorCode::SERVICE_UNAVAILABLE)
                ->detail('Search backend unavailable')
                ->build();

            throw new QueryException(
                config('database.default'),
                'select 1',
                [],
                new HttpErrorException($payload)
            );
        });

        $response = $this->getJson('/test/query-exception-problem');

        $response->assertStatus(503);
        $this->assertErrorResponse($response, ErrorCode::SERVICE_UNAVAILABLE, [
            'detail' => 'Search backend unavailable',
        ]);
    }

    private function typeUri(ErrorCode $code): string
    {
        return config('errors.types.' . $code->value . '.uri');
    }

    private function defaultDetail(ErrorCode $code): string
    {
        return config('errors.types.' . $code->value . '.detail');
    }
}
