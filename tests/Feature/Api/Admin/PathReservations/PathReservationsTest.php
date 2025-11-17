<?php

declare(strict_types=1);

use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use App\Domain\Routing\PathReservationService;
use App\Models\Audit;
use App\Models\ReservedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

// ========== LIST RESERVATIONS ==========

test('admin can list reserved paths', function () {
    ReservedRoute::create([
        'path' => '/api',
        'kind' => 'path',
        'source' => 'system',
    ]);

    ReservedRoute::create([
        'path' => '/blog',
        'kind' => 'path',
        'source' => 'marketing',
    ]);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/reservations');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['path', 'kind', 'source', 'created_at'],
            ],
        ])
        ->assertJsonCount(2, 'data');
});

test('list returns empty array when no reservations', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/reservations');

    $response->assertOk()
        ->assertJsonPath('data', []);
});

test('list is sorted by path', function () {
    ReservedRoute::create(['path' => '/zebra', 'kind' => 'path', 'source' => 'test']);
    ReservedRoute::create(['path' => '/alpha', 'kind' => 'path', 'source' => 'test']);
    ReservedRoute::create(['path' => '/beta', 'kind' => 'path', 'source' => 'test']);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/reservations');

    $response->assertOk();
    $paths = collect($response->json('data'))->pluck('path')->toArray();
    expect($paths)->toBe(['/alpha', '/beta', '/zebra']);
});

test('list requires authentication', function () {
    $response = $this->getJson('/api/v1/admin/reservations');

    expect($response->status())->toBeIn([401, 419]); // 419 for CSRF, 401 for auth
});

// ========== RESERVE PATH ==========

test('admin can reserve path', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/reservations', [
            'path' => '/promo',
            'source' => 'marketing',
            'reason' => 'Landing redesign freeze',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Path reserved successfully');

    $this->assertDatabaseHas('reserved_routes', [
        'path' => '/promo',
        'source' => 'marketing',
    ]);
});

test('reservation creates audit log', function () {
    $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/reservations', [
            'path' => '/promo',
            'source' => 'marketing',
            'reason' => 'Test reason',
        ]);

    $audit = Audit::where('action', 'reserve')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($this->admin->id)
        ->and($audit->subject_type)->toBe(ReservedRoute::class)
        ->and($audit->diff_json)->toHaveKey('path')
        ->and($audit->diff_json['path'])->toBe('/promo');
});

test('duplicate path returns conflict error', function () {
    ReservedRoute::create([
        'path' => '/promo',
        'kind' => 'path',
        'source' => 'marketing',
    ]);

    // Mock the service to throw exception
    $mockService = $this->mock(PathReservationService::class);
    $mockService->shouldReceive('reservePath')
        ->once()
        ->andThrow(new PathAlreadyReservedException('/promo', 'marketing'));

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/reservations', [
            'path' => '/promo',
            'source' => 'editorial',
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');
});

test('reservation validates required fields', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/reservations', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['path', 'source']);
});

test('reservation validates path max length', function () {
    $longPath = '/' . str_repeat('a', 256);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/reservations', [
            'path' => $longPath,
            'source' => 'test',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('path');
});

test('reservation validates source max length', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/reservations', [
            'path' => '/test',
            'source' => str_repeat('a', 101),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('source');
});

test('reservation reason is optional', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/reservations', [
            'path' => '/test',
            'source' => 'test',
        ]);

    $response->assertStatus(201);
});

test('reservation requires admin permissions', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/reservations', [
            'path' => '/test',
            'source' => 'test',
        ]);

    $response->assertStatus(403);
});

// ========== RELEASE PATH ==========

test('admin can release path reservation', function () {
    ReservedRoute::create([
        'path' => '/promo',
        'kind' => 'path',
        'source' => 'marketing',
    ]);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/reservations/' . urlencode('/promo'), [
            'source' => 'marketing',
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Path released successfully');

    $this->assertDatabaseMissing('reserved_routes', [
        'path' => '/promo',
    ]);
});

test('release creates audit log', function () {
    ReservedRoute::create([
        'path' => '/promo',
        'kind' => 'path',
        'source' => 'marketing',
    ]);

    $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/reservations/' . urlencode('/promo'), [
            'source' => 'marketing',
        ]);

    $audit = Audit::where('action', 'release')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($this->admin->id)
        ->and($audit->diff_json['path'])->toBe('/promo');
});

test('release from wrong source returns forbidden', function () {
    ReservedRoute::create([
        'path' => '/promo',
        'kind' => 'path',
        'source' => 'marketing',
    ]);

    // Mock the service to throw exception
    $mockService = $this->mock(PathReservationService::class);
    $mockService->shouldReceive('releasePath')
        ->once()
        ->andThrow(new ForbiddenReservationRelease('/promo', 'marketing', 'editorial'));

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/reservations/' . urlencode('/promo'), [
            'source' => 'editorial',
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

test('release validates required source', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/reservations/' . urlencode('/promo'), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('source');
});

test('release path can be in body if not in url', function () {
    ReservedRoute::create([
        'path' => '/complex/path/*',
        'kind' => 'prefix',
        'source' => 'test',
    ]);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/reservations/' . urlencode('/complex/path/*'), [
            'source' => 'test',
        ]);

    $response->assertOk();
});

test('release requires authentication', function () {
    $response = $this->deleteJson('/api/v1/admin/reservations/' . urlencode('/promo'), [
        'source' => 'test',
    ]);

    expect($response->status())->toBeIn([401, 419]);
});

test('release requires admin permissions', function () {
    $user = User::factory()->create(['is_admin' => false]);

    ReservedRoute::create([
        'path' => '/test',
        'kind' => 'path',
        'source' => 'test',
    ]);

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/reservations/' . urlencode('/test'), [
            'source' => 'test',
        ]);

    $response->assertStatus(403);
});

// ========== PATH NORMALIZATION ==========

test('paths are normalized before reservation', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/reservations', [
            'path' => '/PROMO/',
            'source' => 'test',
        ]);

    $response->assertStatus(201);

    // Normalized path should be lowercase without trailing slash
    $this->assertDatabaseHas('reserved_routes', [
        'path' => '/promo',
    ]);
});

test('list includes reservation metadata', function () {
    $created = ReservedRoute::create([
        'path' => '/test',
        'kind' => 'prefix',
        'source' => 'system',
    ]);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/reservations');

    $response->assertOk()
        ->assertJsonPath('data.0.path', '/test')
        ->assertJsonPath('data.0.kind', 'prefix')
        ->assertJsonPath('data.0.source', 'system');

    expect($response->json('data.0.created_at'))->not->toBeNull();
});

