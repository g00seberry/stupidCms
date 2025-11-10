<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Logging;

use App\Models\User;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

final class ErrorReporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_adds_context_information(): void
    {
        $user = User::factory()->create();

        $request = Request::create('/test', 'GET', server: ['HTTP_X_REQUEST_ID' => 'req-123']);
        $request->setUserResolver(static fn () => $user);

        app()->instance('request', $request);
        auth()->setUser($user);

        $exception = new RuntimeException('boom');

        /** @var ErrorFactory $factory */
        $factory = app(ErrorFactory::class);

        $payload = $factory->for(ErrorCode::INTERNAL_SERVER_ERROR)
            ->detail('Problem occurred')
            ->build();

        Log::shouldReceive('log')->once()->withArgs(function (string $level, string $message, array $context) use ($exception, $user): bool {
            return $level === 'error'
                && $message === 'Internal Server Error'
                && $context['error_code'] === ErrorCode::INTERNAL_SERVER_ERROR->value
                && $context['error_type'] === config('errors.types.' . ErrorCode::INTERNAL_SERVER_ERROR->value . '.uri')
                && $context['status'] === 500
                && $context['request_id'] === 'req-123'
                && $context['user_id'] === $user->getAuthIdentifier()
                && $context['exception'] === $exception;
        })->andReturnNull();

        ErrorReporter::report($exception, $payload, null);

        app()->forgetInstance('request');
    }
}
