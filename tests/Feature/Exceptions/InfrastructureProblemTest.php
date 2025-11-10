<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use App\Support\Http\ProblemType;
use App\Support\Http\Problems\RefreshTokenInternalProblem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

final class InfrastructureProblemTest extends TestCase
{
    public function test_query_exception_returns_neutral_detail(): void
    {
        Route::middleware('api')->get('/test/query-exception', function (): void {
            throw new QueryException('select 1', [], new RuntimeException('Database is unavailable'));
        });

        $response = $this->getJson('/test/query-exception');

        $response->assertStatus(500);
        $response->assertJsonFragment([
            'type' => ProblemType::INTERNAL_ERROR->value,
            'detail' => ProblemType::INTERNAL_ERROR->defaultDetail(),
        ]);
    }

    public function test_query_exception_uses_problem_exception_detail_when_available(): void
    {
        Route::middleware('api')->get('/test/query-exception-problem', function (): void {
            throw new QueryException('select 1', [], new RefreshTokenInternalProblem());
        });

        $problem = new RefreshTokenInternalProblem();

        $response = $this->getJson('/test/query-exception-problem');

        $response->assertStatus(500);
        $response->assertJsonFragment([
            'type' => ProblemType::INTERNAL_ERROR->value,
            'detail' => $problem->userFriendlyDetail(),
        ]);
    }
}
