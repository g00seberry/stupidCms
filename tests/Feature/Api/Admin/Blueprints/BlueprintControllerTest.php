<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Path;
use App\Models\PathRefConstraint;
use App\Models\PostType;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class]);
});

test('можно создать blueprint через API', function () {
    $response = $this->postJson('/api/v1/admin/blueprints', [
        'name' => 'Article',
        'code' => 'article',
        'description' => 'Blog article structure',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'article')
        ->assertJsonPath('data.name', 'Article');

    $this->assertDatabaseHas('blueprints', ['code' => 'article']);
});

test('нельзя создать blueprint с дублирующимся code', function () {
    Blueprint::factory()->create(['code' => 'existing']);

    $response = $this->postJson('/api/v1/admin/blueprints', [
        'name' => 'Test',
        'code' => 'existing',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('можно добавить поле в blueprint', function () {
    $blueprint = Blueprint::factory()->create();

    $response = $this->postJson("/api/v1/admin/blueprints/{$blueprint->id}/paths", [
        'name' => 'title',
        'data_type' => 'string',
        'validation_rules' => ['required' => true],
        'is_indexed' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'title')
        ->assertJsonPath('data.full_path', 'title');

    $this->assertDatabaseHas('paths', [
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
    ]);
});

test('можно обновить blueprint', function () {
    $blueprint = Blueprint::factory()->create(['name' => 'Old Name']);

    $response = $this->putJson("/api/v1/admin/blueprints/{$blueprint->id}", [
        'name' => 'New Name',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    $this->assertDatabaseHas('blueprints', [
        'id' => $blueprint->id,
        'name' => 'New Name',
    ]);
});

test('нельзя удалить blueprint используемый в PostType', function () {
    $blueprint = Blueprint::factory()->create();
    PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    $response = $this->deleteJson("/api/v1/admin/blueprints/{$blueprint->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('detail', 'Невозможно удалить blueprint')
        ->assertJsonPath('meta.reasons', fn($reasons) => is_array($reasons) && count($reasons) > 0);

    $this->assertDatabaseHas('blueprints', ['id' => $blueprint->id]);
});

test('можно удалить неиспользуемый blueprint', function () {
    $blueprint = Blueprint::factory()->create();

    $response = $this->deleteJson("/api/v1/admin/blueprints/{$blueprint->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('blueprints', ['id' => $blueprint->id]);
});

test('получение списка blueprints с пагинацией', function () {
    Blueprint::factory()->count(20)->create();

    $response = $this->getJson('/api/v1/admin/blueprints?per_page=10');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

test('поиск blueprints по name/code', function () {
    $article = Blueprint::factory()->create(['code' => 'article', 'name' => 'Article']);
    Blueprint::factory()->create(['code' => 'page', 'name' => 'Page']);

    $response = $this->getJson('/api/v1/admin/blueprints?search=article');

    $response->assertOk();
    // Может быть больше из-за данных из других тестов, но должно быть минимум 1
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(1);
    // Проверяем, что хотя бы один результат содержит "article"
    $hasArticle = false;
    foreach ($response->json('data') as $blueprint) {
        if (stripos($blueprint['code'] ?? '', 'article') !== false || stripos($blueprint['name'] ?? '', 'article') !== false) {
            $hasArticle = true;
            // Если нашли нужный blueprint, проверяем его код
            if ($blueprint['id'] === $article->id) {
                expect($blueprint['code'])->toBe('article');
            }
            break;
        }
    }
    expect($hasArticle)->toBeTrue();
});

test('можно получить JSON схему blueprint из paths', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'article']);

    // Создать простое поле
    $title = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'validation_rules' => ['required' => true],
        'is_indexed' => true,
        'cardinality' => 'one',
    ]);

    // Создать вложенное поле
    $author = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'json',
        'validation_rules' => ['required' => false],
        'is_indexed' => false,
        'cardinality' => 'one',
    ]);

    $authorName = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $author->id,
        'name' => 'name',
        'full_path' => 'author.name',
        'data_type' => 'string',
        'validation_rules' => ['required' => true],
        'is_indexed' => false,
        'cardinality' => 'one',
    ]);

    $response = $this->getJson("/api/v1/admin/blueprints/{$blueprint->id}/schema");

    $response->assertOk()
        ->assertJsonStructure([
            'schema' => [
                'title' => ['type', 'indexed', 'cardinality', 'validation'],
                'author' => ['type', 'indexed', 'cardinality', 'validation', 'children'],
            ],
        ])
        ->assertJsonPath('schema.title.type', 'string')
        ->assertJsonPath('schema.title.validation.required', true)
        ->assertJsonPath('schema.title.indexed', true)
        ->assertJsonPath('schema.title.cardinality', 'one')
        ->assertJsonPath('schema.author.type', 'json')
        ->assertJsonPath('schema.author.validation.required', false)
        ->assertJsonPath('schema.author.indexed', false)
        ->assertJsonPath('schema.author.children.name.type', 'string')
        ->assertJsonPath('schema.author.children.name.validation.required', true);
});

test('JSON схема blueprint возвращает пустую схему для blueprint без paths', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'empty']);

    $response = $this->getJson("/api/v1/admin/blueprints/{$blueprint->id}/schema");

    $response->assertOk()
        ->assertJsonPath('schema', []);
});

test('JSON схема blueprint правильно обрабатывает многоуровневую вложенность', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'nested']);

    $level1 = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'level1',
        'full_path' => 'level1',
        'data_type' => 'json',
    ]);

    $level2 = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $level1->id,
        'name' => 'level2',
        'full_path' => 'level1.level2',
        'data_type' => 'json',
    ]);

    $level3 = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $level2->id,
        'name' => 'level3',
        'full_path' => 'level1.level2.level3',
        'data_type' => 'string',
    ]);

    $response = $this->getJson("/api/v1/admin/blueprints/{$blueprint->id}/schema");

    $response->assertOk()
        ->assertJsonPath('schema.level1.children.level2.children.level3.type', 'string')
        ->assertJsonPath('schema.level1.children.level2.type', 'json');
});

test('JSON схема blueprint правильно обрабатывает ультрасложную структуру с вложенными массивами объектов и массивами в них', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'ultra_complex']);

    // Массив объектов articles[]
    $articles = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'articles',
        'full_path' => 'articles',
        'data_type' => 'json',
        'cardinality' => 'many',
        'validation_rules' => ['required' => true],
        'is_indexed' => false,
    ]);

    // Поля внутри объекта в массиве articles[].title
    $articleTitle = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $articles->id,
        'name' => 'title',
        'full_path' => 'articles.title',
        'data_type' => 'string',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
        'is_indexed' => true,
    ]);

    // Массив строк внутри объекта в массиве articles[].tags[]
    $articleTags = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $articles->id,
        'name' => 'tags',
        'full_path' => 'articles.tags',
        'data_type' => 'string',
        'cardinality' => 'many',
        'validation_rules' => ['required' => false],
        'is_indexed' => true,
    ]);

    // Массив чисел внутри объекта в массиве articles[].categories[]
    $articleCategories = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $articles->id,
        'name' => 'categories',
        'full_path' => 'articles.categories',
        'data_type' => 'int',
        'cardinality' => 'many',
        'validation_rules' => ['required' => false],
        'is_indexed' => false,
    ]);

    // Объект внутри объекта в массиве articles[].author
    $articleAuthor = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $articles->id,
        'name' => 'author',
        'full_path' => 'articles.author',
        'data_type' => 'json',
        'cardinality' => 'one',
        'validation_rules' => ['required' => false],
        'is_indexed' => false,
    ]);

    // Поля внутри объекта articles[].author.name
    $authorName = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $articleAuthor->id,
        'name' => 'name',
        'full_path' => 'articles.author.name',
        'data_type' => 'string',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
        'is_indexed' => true,
    ]);

    // Массив объектов внутри объекта в массиве articles[].author.contacts[]
    $authorContacts = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $articleAuthor->id,
        'name' => 'contacts',
        'full_path' => 'articles.author.contacts',
        'data_type' => 'json',
        'cardinality' => 'many',
        'validation_rules' => ['required' => false],
        'is_indexed' => false,
    ]);

    // Поля внутри объекта в массиве объектов articles[].author.contacts[].type
    $contactType = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $authorContacts->id,
        'name' => 'type',
        'full_path' => 'articles.author.contacts.type',
        'data_type' => 'string',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
        'is_indexed' => false,
    ]);

    // Поля внутри объекта в массиве объектов articles[].author.contacts[].value
    $contactValue = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $authorContacts->id,
        'name' => 'value',
        'full_path' => 'articles.author.contacts.value',
        'data_type' => 'string',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
        'is_indexed' => true,
    ]);

    // Массив строк внутри объекта в массиве объектов articles[].author.contacts[].metadata[]
    $contactMetadata = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $authorContacts->id,
        'name' => 'metadata',
        'full_path' => 'articles.author.contacts.metadata',
        'data_type' => 'string',
        'cardinality' => 'many',
        'validation_rules' => ['required' => false],
        'is_indexed' => false,
    ]);

    // Массив объектов внутри объекта в массиве объектов articles[].author.contacts[].coordinates[]
    $contactCoordinates = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $authorContacts->id,
        'name' => 'coordinates',
        'full_path' => 'articles.author.contacts.coordinates',
        'data_type' => 'json',
        'cardinality' => 'many',
        'validation_rules' => ['required' => false],
        'is_indexed' => false,
    ]);

    // Поля внутри объекта в массиве объектов в массиве объектов articles[].author.contacts[].coordinates[].lat
    $coordLat = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $contactCoordinates->id,
        'name' => 'lat',
        'full_path' => 'articles.author.contacts.coordinates.lat',
        'data_type' => 'float',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
        'is_indexed' => false,
    ]);

    // Поля внутри объекта в массиве объектов в массиве объектов articles[].author.contacts[].coordinates[].lng
    $coordLng = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $contactCoordinates->id,
        'name' => 'lng',
        'full_path' => 'articles.author.contacts.coordinates.lng',
        'data_type' => 'float',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
        'is_indexed' => false,
    ]);

    $response = $this->getJson("/api/v1/admin/blueprints/{$blueprint->id}/schema");

    $response->assertOk()
        // Проверка массива объектов articles[]
        ->assertJsonPath('schema.articles.type', 'json')
        ->assertJsonPath('schema.articles.cardinality', 'many')
        ->assertJsonPath('schema.articles.validation.required', true)
        ->assertJsonStructure([
            'schema' => [
                'articles' => [
                    'type',
                    'indexed',
                    'cardinality',
                    'validation',
                    'children',
                ],
            ],
        ])
        // Проверка полей внутри объекта в массиве articles[].title
        ->assertJsonPath('schema.articles.children.title.type', 'string')
        ->assertJsonPath('schema.articles.children.title.cardinality', 'one')
        ->assertJsonPath('schema.articles.children.title.validation.required', true)
        ->assertJsonPath('schema.articles.children.title.indexed', true)
        // Проверка массива строк внутри объекта в массиве articles[].tags[]
        ->assertJsonPath('schema.articles.children.tags.type', 'string')
        ->assertJsonPath('schema.articles.children.tags.cardinality', 'many')
        ->assertJsonPath('schema.articles.children.tags.indexed', true)
        // Проверка массива чисел внутри объекта в массиве articles[].categories[]
        ->assertJsonPath('schema.articles.children.categories.type', 'int')
        ->assertJsonPath('schema.articles.children.categories.cardinality', 'many')
        // Проверка объекта внутри объекта в массиве articles[].author
        ->assertJsonPath('schema.articles.children.author.type', 'json')
        ->assertJsonPath('schema.articles.children.author.cardinality', 'one')
        ->assertJsonPath('schema.articles.children.author.children.name.type', 'string')
        ->assertJsonPath('schema.articles.children.author.children.name.validation.required', true)
        // Проверка массива объектов внутри объекта в массиве articles[].author.contacts[]
        ->assertJsonPath('schema.articles.children.author.children.contacts.type', 'json')
        ->assertJsonPath('schema.articles.children.author.children.contacts.cardinality', 'many')
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.type.type', 'string')
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.type.validation.required', true)
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.value.type', 'string')
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.value.indexed', true)
        // Проверка массива строк внутри объекта в массиве объектов articles[].author.contacts[].metadata[]
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.metadata.type', 'string')
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.metadata.cardinality', 'many')
        // Проверка массива объектов внутри объекта в массиве объектов articles[].author.contacts[].coordinates[]
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.coordinates.type', 'json')
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.coordinates.cardinality', 'many')
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.coordinates.children.lat.type', 'float')
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.coordinates.children.lat.validation.required', true)
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.coordinates.children.lat.cardinality', 'one')
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.coordinates.children.lng.type', 'float')
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.coordinates.children.lng.validation.required', true)
        ->assertJsonPath('schema.articles.children.author.children.contacts.children.coordinates.children.lng.cardinality', 'one');
});

// Тесты для constraints в JSON схеме

test('JSON схема blueprint включает constraints для ref-полей', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'article']);

    // Создаём PostType для constraints
    $authorPostType = PostType::factory()->create(['name' => 'Author']);
    $editorPostType = PostType::factory()->create(['name' => 'Editor']);

    // Создаём ref-поле с constraints
    $authorPath = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    PathRefConstraint::factory()->create([
        'path_id' => $authorPath->id,
        'allowed_post_type_id' => $authorPostType->id,
    ]);
    PathRefConstraint::factory()->create([
        'path_id' => $authorPath->id,
        'allowed_post_type_id' => $editorPostType->id,
    ]);

    $response = $this->getJson("/api/v1/admin/blueprints/{$blueprint->id}/schema");

    $response->assertOk()
        ->assertJsonStructure([
            'schema' => [
                'author' => ['type', 'indexed', 'cardinality', 'validation', 'constraints'],
            ],
        ])
        ->assertJsonPath('schema.author.type', 'ref')
        ->assertJsonPath('schema.author.constraints.allowed_post_type_ids', [$authorPostType->id, $editorPostType->id]);
});

test('JSON схема blueprint не включает constraints для не ref-полей', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'article']);

    // Создаём обычное поле (не ref)
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'cardinality' => 'one',
    ]);

    $response = $this->getJson("/api/v1/admin/blueprints/{$blueprint->id}/schema");

    $response->assertOk()
        ->assertJsonStructure([
            'schema' => [
                'title' => ['type', 'indexed', 'cardinality', 'validation'],
            ],
        ])
        ->assertJsonMissingPath('schema.title.constraints');
});

test('JSON схема blueprint не включает constraints для ref-полей без constraints', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'article']);

    // Создаём ref-поле без constraints
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    $response = $this->getJson("/api/v1/admin/blueprints/{$blueprint->id}/schema");

    $response->assertOk()
        ->assertJsonStructure([
            'schema' => [
                'author' => ['type', 'indexed', 'cardinality', 'validation'],
            ],
        ])
        ->assertJsonMissingPath('schema.author.constraints');
});

test('JSON схема blueprint включает constraints для вложенных ref-полей', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'article']);

    $postType = PostType::factory()->create(['name' => 'Author']);

    // Создаём вложенное ref-поле
    $author = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'json',
        'cardinality' => 'one',
    ]);

    $authorRef = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'parent_id' => $author->id,
        'name' => 'ref',
        'full_path' => 'author.ref',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    PathRefConstraint::factory()->create([
        'path_id' => $authorRef->id,
        'allowed_post_type_id' => $postType->id,
    ]);

    $response = $this->getJson("/api/v1/admin/blueprints/{$blueprint->id}/schema");

    $response->assertOk()
        ->assertJsonPath('schema.author.children.ref.constraints.allowed_post_type_ids', [$postType->id]);
});

test('JSON схема blueprint формат constraints соответствует PathResource', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'article']);

    $postType1 = PostType::factory()->create(['name' => 'Author']);
    $postType2 = PostType::factory()->create(['name' => 'Editor']);

    $authorPath = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'ref',
        'cardinality' => 'one',
    ]);

    PathRefConstraint::factory()->create([
        'path_id' => $authorPath->id,
        'allowed_post_type_id' => $postType1->id,
    ]);
    PathRefConstraint::factory()->create([
        'path_id' => $authorPath->id,
        'allowed_post_type_id' => $postType2->id,
    ]);

    $response = $this->getJson("/api/v1/admin/blueprints/{$blueprint->id}/schema");

    $response->assertOk()
        ->assertJsonPath('schema.author.constraints.allowed_post_type_ids', function ($ids) use ($postType1, $postType2) {
            return is_array($ids)
                && count($ids) === 2
                && in_array($postType1->id, $ids, true)
                && in_array($postType2->id, $ids, true);
        });
});

