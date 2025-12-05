<?php

declare(strict_types=1);

use App\Domain\Search\Jobs\ReindexSearchJob;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->postType = PostType::factory()->create(['name' => 'Article']);
    
    // Enable search by default
    Config::set('search.enabled', true);
    Config::set('search.batch.size', 500);
});

test('admin can trigger reindex', function () {
    Bus::fake();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/search/reindex');

    $response->assertStatus(202)
        ->assertJsonStructure([
            'job_id',
            'batch_size',
            'estimated_total',
        ]);

    Bus::assertDispatched(ReindexSearchJob::class);
});

test('reindex job is dispatched with tracking id', function () {
    Bus::fake();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/search/reindex');

    $response->assertStatus(202);

    Bus::assertDispatched(ReindexSearchJob::class, function (ReindexSearchJob $job) use ($response) {
        $jobId = $response->json('job_id');
        
        // Check if the job was created with the same tracking ID
        return $job->trackingId === $jobId;
    });
});

test('reindex returns batch size from config', function () {
    Bus::fake();
    Config::set('search.batch.size', 250);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/search/reindex');

    $response->assertStatus(202)
        ->assertJsonPath('batch_size', 250);
});

test('reindex returns estimated total from published entries', function () {
    Bus::fake();

    // Create some published and draft entries
    Entry::factory()->count(5)->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'published',
        'published_at' => now(),
    ]);

    Entry::factory()->count(3)->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/search/reindex');

    $response->assertStatus(202)
        ->assertJsonPath('estimated_total', 5); // Only published entries
});

test('reindex fails when search is disabled', function () {
    Config::set('search.enabled', false);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/search/reindex');

    $response->assertStatus(503)
        ->assertJsonPath('code', 'SERVICE_UNAVAILABLE')
        ->assertJsonPath('detail', 'Search service is disabled.');
});

test('reindex requires authentication', function () {
    Bus::fake();

    $response = $this->postJson('/api/v1/admin/search/reindex');

    // Expecting 401 or redirect (depending on middleware config)
    expect($response->status())->toBeIn([401, 419]); // 419 for CSRF, 401 for auth
    
    Bus::assertNotDispatched(ReindexSearchJob::class);
});

test('reindex returns unique job id', function () {
    Bus::fake();

    $response1 = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/search/reindex');

    $response2 = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/search/reindex');

    $jobId1 = $response1->json('job_id');
    $jobId2 = $response2->json('job_id');

    expect($jobId1)->not->toBe($jobId2)
        ->and($jobId1)->toBeString()
        ->and($jobId2)->toBeString();
});

test('reindex job id is a valid ulid', function () {
    Bus::fake();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/search/reindex');

    $jobId = $response->json('job_id');

    // ULID format: 26 characters, uppercase alphanumeric
    expect($jobId)->toBeString()
        ->toHaveLength(26)
        ->toMatch('/^[0-9A-Z]{26}$/');
});

test('reindex with zero published entries returns zero estimated total', function () {
    Bus::fake();

    // Create only draft entries
    Entry::factory()->count(3)->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/search/reindex');

    $response->assertStatus(202)
        ->assertJsonPath('estimated_total', 0);
});

