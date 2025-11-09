<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Search;

use App\Domain\Search\Jobs\ReindexSearchJob;
use App\Http\Middleware\JwtAuth;
use App\Http\Middleware\VerifyApiCsrf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ReindexSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Config::set('search.enabled', true);
        $this->withoutMiddleware([JwtAuth::class, VerifyApiCsrf::class]);
    }

    public function test_requires_permission_for_reindex(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, guard: 'api');

        $response = $this->postJson('/api/v1/admin/search/reindex');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_starts_reindex_and_returns_accepted_with_job_id(): void
    {
        $user = User::factory()->create();
        $user->grantAdminPermissions('search.reindex');
        $user->save();

        $this->actingAs($user, guard: 'api');

        $response = $this->postJson('/api/v1/admin/search/reindex');

        $response->assertStatus(Response::HTTP_ACCEPTED);
        $response->assertHeader('Cache-Control', 'no-store, private');

        $payload = $response->json();
        self::assertIsArray($payload);
        self::assertArrayHasKey('job_id', $payload);

        Bus::assertDispatched(ReindexSearchJob::class, function (ReindexSearchJob $job) use ($payload): bool {
            return $job->trackingId === $payload['job_id'];
        });
    }

    public function test_returns_service_unavailable_when_search_disabled(): void
    {
        Config::set('search.enabled', false);

        $user = User::factory()->create();
        $user->grantAdminPermissions('search.reindex');
        $user->save();

        $this->actingAs($user, guard: 'api');

        $response = $this->postJson('/api/v1/admin/search/reindex');

        $response->assertStatus(Response::HTTP_SERVICE_UNAVAILABLE);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'title' => 'Service Unavailable',
        ]);
    }
}


