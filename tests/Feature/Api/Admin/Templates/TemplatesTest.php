<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->templatesPath = resource_path('views');
    
    // Clean up test templates after each test
    $this->testTemplates = [];
});

afterEach(function () {
    // Clean up ONLY templates tracked during test execution
    foreach ($this->testTemplates as $templatePath) {
        if (File::exists($templatePath) && File::isFile($templatePath)) {
            File::delete($templatePath);
        }
    }
    
    // Remove ONLY completely empty test directories (no production files)
    $testDirs = [
        resource_path('views/test'),
        resource_path('views/pages/nested'),
        resource_path('views/pages/article'),
        resource_path('views/pages'),
    ];
    
    foreach ($testDirs as $dir) {
        if (File::isDirectory($dir)) {
            $files = File::allFiles($dir);
            $subdirs = File::directories($dir);
            // Only delete if completely empty
            if (empty($files) && empty($subdirs)) {
                File::deleteDirectory($dir);
            }
        }
    }
});

// ========== LIST TEMPLATES ==========

test('admin can list templates', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/templates');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['name', 'path', 'exists'],
            ],
        ]);
});

test('list excludes system directories', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/templates');

    $response->assertOk();
    
    $templates = collect($response->json('data'));
    
    // Should not include templates from excluded directories
    $excludedPrefixes = ['admin.', 'errors.', 'layouts.', 'partials.', 'vendor.'];
    
    foreach ($templates as $template) {
        $name = $template['name'];
        foreach ($excludedPrefixes as $prefix) {
            expect($name)->not->toStartWith($prefix);
        }
    }
});

test('list returns sorted templates', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/templates');

    $response->assertOk();
    
    $templateNames = collect($response->json('data'))->pluck('name')->toArray();
    $sortedNames = $templateNames;
    sort($sortedNames);
    
    expect($templateNames)->toBe($sortedNames);
});

test('list requires authentication', function () {
    $response = $this->getJson('/api/v1/admin/templates');

    expect($response->status())->toBeIn([401, 419]);
});

// ========== SHOW TEMPLATE ==========

test('admin can view template content', function () {
    // Create a test template
    $templatePath = resource_path('views/test/show.blade.php');
    File::ensureDirectoryExists(dirname($templatePath));
    File::put($templatePath, '<div>Test Content</div>');
    $this->testTemplates[] = $templatePath;

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/templates/test.show');

    $response->assertOk()
        ->assertJsonPath('data.name', 'test.show')
        ->assertJsonPath('data.path', 'test/show.blade.php')
        ->assertJsonPath('data.exists', true)
        ->assertJsonPath('data.content', '<div>Test Content</div>');
});

test('show returns 404 for non-existent template', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/templates/non.existent');

    $response->assertStatus(404)
        ->assertJsonPath('code', 'NOT_FOUND');
});

test('show requires authentication', function () {
    $response = $this->getJson('/api/v1/admin/templates/test.template');

    expect($response->status())->toBeIn([401, 419]);
});

// ========== CREATE TEMPLATE ==========

test('admin can create template', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/templates', [
            'name' => 'pages.custom',
            'content' => '<div>New Template</div>',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'pages.custom')
        ->assertJsonPath('data.path', 'pages/custom.blade.php')
        ->assertJsonPath('data.exists', true);

    $templatePath = resource_path('views/pages/custom.blade.php');
    $this->testTemplates[] = $templatePath;
    
    expect(File::exists($templatePath))->toBeTrue();
    expect(File::get($templatePath))->toBe('<div>New Template</div>');
});

test('create returns conflict if template exists', function () {
    // Create template first
    $templatePath = resource_path('views/pages/existing.blade.php');
    File::ensureDirectoryExists(dirname($templatePath));
    File::put($templatePath, '<div>Existing</div>');
    $this->testTemplates[] = $templatePath;

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/templates', [
            'name' => 'pages.existing',
            'content' => '<div>New</div>',
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');
});

test('create validates required fields', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/templates', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'content']);
});

test('create automatically creates directories', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/templates', [
            'name' => 'pages.nested.deep',
            'content' => '<div>Deep</div>',
        ]);

    $response->assertStatus(201);

    $templatePath = resource_path('views/pages/nested/deep.blade.php');
    $this->testTemplates[] = $templatePath;
    
    expect(File::exists($templatePath))->toBeTrue();
});

test('create requires authentication', function () {
    $response = $this->postJson('/api/v1/admin/templates', [
        'name' => 'test.template',
        'content' => '<div>Test</div>',
    ]);

    expect($response->status())->toBeIn([401, 419]);
});

// ========== UPDATE TEMPLATE ==========

test('admin can update template', function () {
    // Create template first
    $templatePath = resource_path('views/pages/update.blade.php');
    File::ensureDirectoryExists(dirname($templatePath));
    File::put($templatePath, '<div>Original</div>');
    $this->testTemplates[] = $templatePath;

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/templates/pages.update', [
            'content' => '<div>Updated</div>',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'pages.update')
        ->assertJsonPath('data.exists', true);

    expect(File::get($templatePath))->toBe('<div>Updated</div>');
});

test('update returns 404 for non-existent template', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/templates/non.existent', [
            'content' => '<div>Test</div>',
        ]);

    $response->assertStatus(404)
        ->assertJsonPath('code', 'NOT_FOUND');
});

test('update validates content required', function () {
    // Create template first
    $templatePath = resource_path('views/pages/validate.blade.php');
    File::ensureDirectoryExists(dirname($templatePath));
    File::put($templatePath, '<div>Original</div>');
    $this->testTemplates[] = $templatePath;

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/templates/pages.validate', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('content');
});

test('update requires authentication', function () {
    $response = $this->putJson('/api/v1/admin/templates/test.template', [
        'content' => '<div>Test</div>',
    ]);

    expect($response->status())->toBeIn([401, 419]);
});

// ========== TEMPLATE NAME CONVERSION ==========

test('template name converts to correct path', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/templates', [
            'name' => 'pages.article.show',
            'content' => '<div>Test</div>',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.path', 'pages/article/show.blade.php');

    $this->testTemplates[] = resource_path('views/pages/article/show.blade.php');
});

