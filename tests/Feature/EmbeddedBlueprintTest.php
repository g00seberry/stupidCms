<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
});

test('можно создать Path с data_type=blueprint', function () {
    $postType = PostType::factory()->create();
    $mainBlueprint = Blueprint::factory()->create([
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);
    
    $component = Blueprint::factory()->create([
        'type' => 'component',
        'post_type_id' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$mainBlueprint->id}/paths", [
            'blueprint_id' => $mainBlueprint->id,
            'name' => 'hero',
            'full_path' => 'hero',
            'data_type' => 'blueprint',
            'cardinality' => 'one',
            'is_indexed' => false,
            'embedded_blueprint_id' => $component->id,
        ]);

    $response->assertStatus(201);
    expect($response->json('data.data_type'))->toBe('blueprint');
    expect($response->json('data.embedded_blueprint_id'))->toBe($component->id);
});

test('материализация создаёт дочерние Paths при создании embedded поля', function () {
    $postType = PostType::factory()->create();
    $mainBlueprint = Blueprint::factory()->create([
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);
    
    $component = Blueprint::factory()->create([
        'type' => 'component',
        'post_type_id' => null,
    ]);

    // Создать поля в компоненте
    Path::create([
        'blueprint_id' => $component->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'cardinality' => 'one',
        'is_indexed' => true,
    ]);

    Path::create([
        'blueprint_id' => $component->id,
        'name' => 'subtitle',
        'full_path' => 'subtitle',
        'data_type' => 'string',
        'cardinality' => 'one',
        'is_indexed' => true,
    ]);

    // Создать embedded поле
    $heroField = Path::create([
        'blueprint_id' => $mainBlueprint->id,
        'name' => 'hero',
        'full_path' => 'hero',
        'data_type' => 'blueprint',
        'cardinality' => 'one',
        'is_indexed' => false,
        'embedded_blueprint_id' => $component->id,
    ]);

    // Проверить материализованные Paths
    $materializedPaths = Path::where('embedded_root_path_id', $heroField->id)->get();
    
    expect($materializedPaths)->toHaveCount(2);
    expect($materializedPaths->pluck('full_path')->toArray())->toContain('hero.title', 'hero.subtitle');
    expect($materializedPaths->pluck('source_component_id')->unique()->first())->toBe($component->id);
});

test('материализованные поля индексируются корректно', function () {
    $postType = PostType::factory()->create();
    $mainBlueprint = Blueprint::factory()->create([
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);
    
    $component = Blueprint::factory()->create([
        'type' => 'component',
        'post_type_id' => null,
    ]);

    // Создать поля в компоненте
    Path::create([
        'blueprint_id' => $component->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'cardinality' => 'one',
        'is_indexed' => true,
    ]);

    // Создать embedded поле
    Path::create([
        'blueprint_id' => $mainBlueprint->id,
        'name' => 'hero',
        'full_path' => 'hero',
        'data_type' => 'blueprint',
        'cardinality' => 'one',
        'is_indexed' => false,
        'embedded_blueprint_id' => $component->id,
    ]);

    // Создать Entry с данными
    $entry = Entry::factory()->create([
        'blueprint_id' => $mainBlueprint->id,
        'post_type_id' => $postType->id,
        'data_json' => [
            'hero' => [
                'title' => 'Test Hero Title',
            ],
        ],
    ]);

    // Проверить индексацию
    expect($entry->values()->count())->toBe(1);
    $value = $entry->values()->first();
    expect($value->path->full_path)->toBe('hero.title');
    expect($value->value_string)->toBe('Test Hero Title');
});

test('нельзя создать Path с data_type=blueprint без embedded_blueprint_id', function () {
    $postType = PostType::factory()->create();
    $mainBlueprint = Blueprint::factory()->create([
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$mainBlueprint->id}/paths", [
            'blueprint_id' => $mainBlueprint->id,
            'name' => 'hero',
            'full_path' => 'hero',
            'data_type' => 'blueprint',
            'cardinality' => 'one',
            'is_indexed' => false,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('embedded_blueprint_id');
});

test('нельзя установить is_indexed=true для data_type=blueprint', function () {
    $postType = PostType::factory()->create();
    $mainBlueprint = Blueprint::factory()->create([
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);
    
    $component = Blueprint::factory()->create([
        'type' => 'component',
        'post_type_id' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$mainBlueprint->id}/paths", [
            'blueprint_id' => $mainBlueprint->id,
            'name' => 'hero',
            'full_path' => 'hero',
            'data_type' => 'blueprint',
            'cardinality' => 'one',
            'is_indexed' => true,
            'embedded_blueprint_id' => $component->id,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('is_indexed');
});

test('embedded_blueprint_id должен указывать на component', function () {
    $postType = PostType::factory()->create();
    $mainBlueprint = Blueprint::factory()->create([
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);
    
    $anotherFullBlueprint = Blueprint::factory()->create([
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$mainBlueprint->id}/paths", [
            'blueprint_id' => $mainBlueprint->id,
            'name' => 'hero',
            'full_path' => 'hero',
            'data_type' => 'blueprint',
            'cardinality' => 'one',
            'is_indexed' => false,
            'embedded_blueprint_id' => $anotherFullBlueprint->id,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('embedded_blueprint_id');
});

test('нельзя встроить Blueprint сам в себя', function () {
    $component = Blueprint::factory()->create([
        'type' => 'component',
        'post_type_id' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$component->id}/paths", [
            'blueprint_id' => $component->id,
            'name' => 'recursive',
            'full_path' => 'recursive',
            'data_type' => 'blueprint',
            'cardinality' => 'one',
            'is_indexed' => false,
            'embedded_blueprint_id' => $component->id,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('embedded_blueprint_id');
});

test('при удалении embedded поля удаляются материализованные Paths', function () {
    $postType = PostType::factory()->create();
    $mainBlueprint = Blueprint::factory()->create([
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);
    
    $component = Blueprint::factory()->create([
        'type' => 'component',
        'post_type_id' => null,
    ]);

    // Создать поля в компоненте
    Path::create([
        'blueprint_id' => $component->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'cardinality' => 'one',
        'is_indexed' => true,
    ]);

    // Создать embedded поле
    $heroField = Path::create([
        'blueprint_id' => $mainBlueprint->id,
        'name' => 'hero',
        'full_path' => 'hero',
        'data_type' => 'blueprint',
        'cardinality' => 'one',
        'is_indexed' => false,
        'embedded_blueprint_id' => $component->id,
    ]);

    // Проверить, что материализованные Paths созданы
    expect(Path::where('embedded_root_path_id', $heroField->id)->count())->toBe(1);

    // Удалить embedded поле
    $heroField->delete();

    // Проверить, что материализованные Paths удалены
    expect(Path::where('embedded_root_path_id', $heroField->id)->count())->toBe(0);
});

test('при изменении компонента синхронизируются материализованные Paths', function () {
    $postType = PostType::factory()->create();
    $mainBlueprint = Blueprint::factory()->create([
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);
    
    $component = Blueprint::factory()->create([
        'type' => 'component',
        'post_type_id' => null,
    ]);

    // Создать поле в компоненте
    $titlePath = Path::create([
        'blueprint_id' => $component->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'cardinality' => 'one',
        'is_indexed' => true,
    ]);

    // Создать embedded поле
    Path::create([
        'blueprint_id' => $mainBlueprint->id,
        'name' => 'hero',
        'full_path' => 'hero',
        'data_type' => 'blueprint',
        'cardinality' => 'one',
        'is_indexed' => false,
        'embedded_blueprint_id' => $component->id,
    ]);

    // Изменить поле в компоненте
    $titlePath->update([
        'data_type' => 'text',
        'cardinality' => 'many',
        'is_required' => false,
    ]);

    // Проверить, что материализованное поле обновилось
    $materializedPath = Path::where('source_path_id', $titlePath->id)->first();
    expect($materializedPath->data_type)->toBe('text');
    expect($materializedPath->cardinality)->toBe('many');
});

test('можно встроить компонент в агрегатор с cardinality=many', function () {
    $postType = PostType::factory()->create();
    $mainBlueprint = Blueprint::factory()->create([
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);
    
    $blockComponent = Blueprint::factory()->create([
        'type' => 'component',
        'post_type_id' => null,
    ]);

    // Создать поля в компоненте
    Path::create([
        'blueprint_id' => $blockComponent->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'cardinality' => 'one',
        'is_indexed' => true,
    ]);

    Path::create([
        'blueprint_id' => $blockComponent->id,
        'name' => 'image',
        'full_path' => 'image',
        'data_type' => 'string',
        'cardinality' => 'one',
        'is_indexed' => false,
    ]);

    // Создать агрегатор с cardinality=many
    $blocksField = Path::create([
        'blueprint_id' => $mainBlueprint->id,
        'name' => 'blocks',
        'full_path' => 'blocks',
        'data_type' => 'blueprint',
        'cardinality' => 'many',
        'is_indexed' => false,
        'embedded_blueprint_id' => $blockComponent->id,
    ]);

    // Проверить, что материализованные Paths созданы
    $materializedPaths = Path::where('embedded_root_path_id', $blocksField->id)->get();
    expect($materializedPaths)->toHaveCount(2);
    expect($materializedPaths->pluck('full_path')->toArray())->toContain('blocks.title', 'blocks.image');
});
