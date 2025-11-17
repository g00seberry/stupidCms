<?php

declare(strict_types=1);

use App\Models\Option;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
});

// INDEX tests
test('admin can list options by namespace', function () {
    Option::factory()->create(['namespace' => 'site', 'key' => 'title', 'value_json' => 'My Site']);
    Option::factory()->create(['namespace' => 'site', 'key' => 'tagline', 'value_json' => 'Best CMS']);
    Option::factory()->create(['namespace' => 'theme', 'key' => 'color', 'value_json' => 'blue']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/options/site');

    $response->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'namespace', 'key', 'value', 'description', 'updated_at', 'deleted_at'],
            ],
            'links',
            'meta',
        ]);
});

test('options list is paginated', function () {
    Option::factory()->count(30)->create(['namespace' => 'site']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/options/site?per_page=10');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 30)
        ->assertJsonCount(10, 'data');
});

test('options can be searched by key', function () {
    Option::factory()->create(['namespace' => 'site', 'key' => 'hero.title']);
    Option::factory()->create(['namespace' => 'site', 'key' => 'footer.text']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/options/site?q=hero');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.key', 'hero.title');
});

test('options can be searched by description', function () {
    Option::factory()->create(['namespace' => 'site', 'key' => 'title', 'description' => 'Site title']);
    Option::factory()->create(['namespace' => 'site', 'key' => 'email', 'description' => 'Contact email']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/options/site?q=email');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.key', 'email');
});

test('options list can include soft deleted', function () {
    Option::factory()->create(['namespace' => 'site', 'key' => 'active']);
    Option::factory()->create(['namespace' => 'site', 'key' => 'deleted', 'deleted_at' => now()]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/options/site?deleted=with');

    $response->assertOk()
        ->assertJsonPath('meta.total', 2);
});

test('options list can show only soft deleted', function () {
    Option::factory()->create(['namespace' => 'site', 'key' => 'active']);
    Option::factory()->create(['namespace' => 'site', 'key' => 'deleted', 'deleted_at' => now()]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/options/site?deleted=only');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.key', 'deleted');
});

test('options are sorted by key', function () {
    Option::factory()->create(['namespace' => 'site', 'key' => 'z-last']);
    Option::factory()->create(['namespace' => 'site', 'key' => 'a-first']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/options/site');

    $response->assertOk()
        ->assertJsonPath('data.0.key', 'a-first')
        ->assertJsonPath('data.1.key', 'z-last');
});

// SHOW tests
test('admin can view single option', function () {
    Option::factory()->create([
        'namespace' => 'site',
        'key' => 'title',
        'value_json' => 'My Site',
        'description' => 'Site title',
    ]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/options/site/title');

    $response->assertOk()
        ->assertJsonPath('data.namespace', 'site')
        ->assertJsonPath('data.key', 'title')
        ->assertJsonPath('data.value', 'My Site')
        ->assertJsonPath('data.description', 'Site title');
});

test('show returns 404 for non-existent option', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/options/site/non-existent');

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

// PUT tests (upsert)
test('admin can create new option', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/options/site/title', [
            'value' => 'New Site Title',
            'description' => 'The site title',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.namespace', 'site')
        ->assertJsonPath('data.key', 'title')
        ->assertJsonPath('data.value', 'New Site Title')
        ->assertJsonPath('data.description', 'The site title');

    expect(Option::where('namespace', 'site')->where('key', 'title')->exists())->toBeTrue();
});

test('admin can update existing option', function () {
    Option::factory()->create([
        'namespace' => 'site',
        'key' => 'title',
        'value_json' => 'Old Title',
        'description' => 'Old description',
    ]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/options/site/title', [
            'value' => 'New Title',
            'description' => 'New description',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.value', 'New Title')
        ->assertJsonPath('data.description', 'New description');
});

test('option can store array values', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/options/site/menu', [
            'value' => ['home', 'about', 'contact'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.value', ['home', 'about', 'contact']);
});

test('option can store object values', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/options/site/config', [
            'value' => ['theme' => 'dark', 'lang' => 'en'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.value.theme', 'dark')
        ->assertJsonPath('data.value.lang', 'en');
});

test('option description is optional', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/options/site/title', [
            'value' => 'Title without description',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.value', 'Title without description');
});

test('put dispatches option changed event', function () {
    Event::fake();

    actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/options/site/title', [
            'value' => 'New Value',
        ]);

    Event::assertDispatched(\App\Events\OptionChanged::class, function ($event) {
        return $event->namespace === 'site' && $event->key === 'title';
    });
});

// DELETE tests
test('admin can delete option', function () {
    $option = Option::factory()->create(['namespace' => 'site', 'key' => 'temp']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/options/site/temp');

    $response->assertNoContent();

    $deletedOption = Option::withTrashed()->where('namespace', 'site')->where('key', 'temp')->first();
    expect($deletedOption)->not->toBeNull()
        ->and($deletedOption->trashed())->toBeTrue();
});

test('delete returns 404 for non-existent option', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/options/site/non-existent');

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

// RESTORE tests
test('admin can restore deleted option', function () {
    $option = Option::factory()->create(['namespace' => 'site', 'key' => 'restored']);
    $option->delete();

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/options/site/restored/restore');

    $response->assertOk()
        ->assertJsonPath('data.key', 'restored')
        ->assertJsonPath('data.deleted_at', null);

    expect(Option::where('namespace', 'site')->where('key', 'restored')->first()->trashed())->toBeFalse();
});

test('restore returns 404 for non-existent option', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/options/site/non-existent/restore');

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

test('restore on non-deleted option returns the option unchanged', function () {
    $option = Option::factory()->create(['namespace' => 'site', 'key' => 'active']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/options/site/active/restore');

    $response->assertOk()
        ->assertJsonPath('data.key', 'active')
        ->assertJsonPath('data.deleted_at', null);
});

// VALIDATION tests
test('invalid namespace returns validation error', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/options/INVALID-NAMESPACE');

    $response->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_OPTION_IDENTIFIER');
});

test('invalid key returns validation error', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/options/site/INVALID-KEY!');

    $response->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_OPTION_IDENTIFIER');
});

