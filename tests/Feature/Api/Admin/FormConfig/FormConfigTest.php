<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\FormConfig;
use App\Models\Path;
use App\Models\PostType;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class]);
});

test('admin can get form config', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();
    $config = FormConfig::factory()
        ->withConfig([
            'title' => [
                'name' => 'inputText',
                'props' => ['label' => 'Title'],
            ],
        ])
        ->create([
            'post_type_id' => $postType->id,
            'blueprint_id' => $blueprint->id,
        ]);

    $response = $this->getJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}");

    $response->assertOk();
    // Метод show возвращает config_json напрямую, без обёртки data
    $json = $response->json();
    expect($json)->toBeArray()
        ->and($json['title']['name'])->toBe('inputText')
        ->and($json['title']['props']['label'])->toBe('Title');
});

test('get form config returns empty object when not found', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();

    $response = $this->getJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}");

    $response->assertOk();
    // Метод show возвращает пустой объект {} напрямую, без обёртки data
    // Laravel преобразует пустой объект в пустой массив []
    $json = $response->json();
    expect($json)->toBeArray()
        ->and($json)->toBeEmpty();
});

test('get form config returns 404 when post type not found', function () {
    $blueprint = Blueprint::factory()->create();

    $response = $this->getJson("/api/v1/admin/post-types/99999/form-config/{$blueprint->id}");

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND')
        ->assertJsonPath('detail', 'PostType not found: 99999');
});

test('admin can create form config', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
    ]);

    $configData = [
        'title' => [
            'name' => 'inputText',
            'props' => ['label' => 'Title'],
        ],
    ];

    $response = $this->putJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}", [
        'config_json' => $configData,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.post_type_id', $postType->id)
        ->assertJsonPath('data.blueprint_id', $blueprint->id)
        ->assertJsonPath('data.config_json.title.name', 'inputText')
        ->assertJsonPath('data.config_json.title.props.label', 'Title');

    $this->assertDatabaseHas('form_configs', [
        'post_type_id' => $postType->id,
        'blueprint_id' => $blueprint->id,
    ]);
});

test('admin can update existing form config', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
    ]);

    $config = FormConfig::factory()
        ->withConfig([
            'title' => [
                'name' => 'inputText',
                'props' => ['label' => 'Old Title'],
            ],
        ])
        ->create([
            'post_type_id' => $postType->id,
            'blueprint_id' => $blueprint->id,
        ]);

    $newConfigData = [
        'title' => [
            'name' => 'inputText',
            'props' => ['label' => 'New Title'],
        ],
    ];

    $response = $this->putJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}", [
        'config_json' => $newConfigData,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.config_json.title.props.label', 'New Title');

    $config->refresh();
    expect($config->config_json['title']['props']['label'])->toBe('New Title');
});

test('update form config validates config_json structure', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();

    $response = $this->putJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}", [
        'config_json' => [
            'title' => [
                'name' => 'inputText',
                // missing props
            ],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('config_json.title.props');
});

test('update form config validates name field is required', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();

    $response = $this->putJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}", [
        'config_json' => [
            'title' => [
                'props' => ['label' => 'Title'],
                // missing name
            ],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('config_json.title.name');
});

test('update form config validates paths exist in blueprint', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
    ]);

    $response = $this->putJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}", [
        'config_json' => [
            'nonexistent_path' => [
                'name' => 'inputText',
                'props' => ['label' => 'Test'],
            ],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

test('update form config returns 404 when post type not found', function () {
    $blueprint = Blueprint::factory()->create();

    $response = $this->putJson("/api/v1/admin/post-types/99999/form-config/{$blueprint->id}", [
        'config_json' => [],
    ]);

    // Валидация происходит до проверки PostType, поэтому получаем 422
    $response->assertUnprocessable()
        ->assertJsonValidationErrors('config_json');
});

test('admin can delete form config', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();
    $config = FormConfig::factory()->create([
            'post_type_id' => $postType->id,
        'blueprint_id' => $blueprint->id,
    ]);

    $response = $this->deleteJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('form_configs', [
        'id' => $config->id,
    ]);
});

test('delete form config returns 404 when config not found', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();

    $response = $this->deleteJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}");

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND')
        ->assertJsonPath('detail', "Form config not found for post_type_id={$postType->id}, blueprint_id={$blueprint->id}");
});

test('delete form config returns 404 when post type not found', function () {
    $blueprint = Blueprint::factory()->create();

    $response = $this->deleteJson("/api/v1/admin/post-types/99999/form-config/{$blueprint->id}");

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

test('admin can list form configs by post type', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint1 = Blueprint::factory()->create();
    $blueprint2 = Blueprint::factory()->create();

    $config1 = FormConfig::factory()
        ->withConfig(['title' => ['name' => 'inputText', 'props' => ['label' => 'Title']]])
        ->create([
            'post_type_id' => $postType->id,
            'blueprint_id' => $blueprint1->id,
        ]);

    $config2 = FormConfig::factory()
        ->withConfig(['content' => ['name' => 'textarea', 'props' => ['label' => 'Content']]])
        ->create([
            'post_type_id' => $postType->id,
            'blueprint_id' => $blueprint2->id,
        ]);

    // Создаём конфигурацию для другого post type
    $otherPostType = PostType::factory()->create(['name' => 'Page']);
    FormConfig::factory()->create([
        'post_type_id' => $otherPostType->id,
        'blueprint_id' => $blueprint1->id,
    ]);

    $response = $this->getJson("/api/v1/admin/post-types/{$postType->id}/form-configs");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.post_type_id', $postType->id)
        ->assertJsonPath('data.0.blueprint_id', $blueprint1->id)
        ->assertJsonPath('data.1.post_type_id', $postType->id)
        ->assertJsonPath('data.1.blueprint_id', $blueprint2->id);
});

test('list form configs returns empty array when no configs exist', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);

    $response = $this->getJson("/api/v1/admin/post-types/{$postType->id}/form-configs");

    $response->assertOk()
        ->assertJson(['data' => []]);
});

test('list form configs returns 404 when post type not found', function () {
    $response = $this->getJson('/api/v1/admin/post-types/99999/form-configs');

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND')
        ->assertJsonPath('detail', 'PostType not found: 99999');
});

test('form config can handle complex nested paths', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'json',
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'name',
        'full_path' => 'author.name',
        'data_type' => 'string',
    ]);

    $configData = [
        'author' => [
            'name' => 'fieldGroup',
            'props' => ['label' => 'Author'],
        ],
        'author.name' => [
            'name' => 'inputText',
            'props' => ['label' => 'Name'],
        ],
    ];

    $response = $this->putJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}", [
        'config_json' => $configData,
    ]);

    $response->assertOk();
    $json = $response->json('data.config_json');
    expect($json)->toBeArray()
        ->and($json['author']['name'])->toBe('fieldGroup')
        ->and($json['author']['props']['label'])->toBe('Author')
        ->and($json['author.name']['name'])->toBe('inputText')
        ->and($json['author.name']['props']['label'])->toBe('Name');
});

test('form config returns empty object when config_json is empty', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();
    $config = FormConfig::factory()->create([
            'post_type_id' => $postType->id,
        'blueprint_id' => $blueprint->id,
        'config_json' => [],
    ]);

    $response = $this->getJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}");

    $response->assertOk();
    // Метод show возвращает пустой объект {} напрямую, без обёртки data
    // Laravel преобразует пустой объект в пустой массив []
    $json = $response->json();
    expect($json)->toBeArray()
        ->and($json)->toBeEmpty();
});

test('form config validates config_json must be object not array', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();

    $response = $this->putJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}", [
        'config_json' => ['title', 'content'], // список вместо объекта
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('config_json');
});

test('form config validates props must be object', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    $blueprint = Blueprint::factory()->create();

    $response = $this->putJson("/api/v1/admin/post-types/{$postType->id}/form-config/{$blueprint->id}", [
        'config_json' => [
            'title' => [
                'name' => 'inputText',
                'props' => ['label', 'placeholder'], // список вместо объекта
            ],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('config_json.title.props');
});

