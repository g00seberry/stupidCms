<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Logging;

use App\Models\User;
use App\Support\Http\ProblemType;
use App\Support\Logging\ProblemReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

final class ProblemReporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_adds_context_information(): void
    {
        $user = User::factory()->create();

        $request = Request::create('/test', 'GET', server: ['HTTP_X_REQUEST_ID' => 'req-123']);
        $request->setUserResolver(static fn () => $user);

        app()->instance('request', $request);
        auth()->setUser($user);

        Log::spy();

        $exception = new RuntimeException('boom');

        ProblemReporter::report($exception, ProblemType::INTERNAL_ERROR, 'Problem occurred', ['foo' => 'bar']);

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($exception, $user): bool {
            return $message === 'Problem occurred'
                && $context['foo'] === 'bar'
                && $context['problem_type'] === ProblemType::INTERNAL_ERROR->value
                && $context['request_id'] === 'req-123'
                && $context['user_id'] === $user->getAuthIdentifier()
                && $context['exception'] === $exception;
        });

        app()->forgetInstance('request');
    }
}
