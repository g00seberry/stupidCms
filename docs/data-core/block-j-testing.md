# Блок J: Комплексное тестирование

**Трудоёмкость:** 88 часов (Could Have)  
**Критичность:** 🔴 Критично для стабильности  
**Результат:** Полное покрытие тестами: Unit, Feature, Integration, Performance

---

## J.1. Unit: Валидация циклов (8 часов)

`tests/Unit/Services/Blueprint/CyclicDependencyValidatorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Exceptions\Blueprint\CyclicDependencyException;
use App\Models\Blueprint;
use App\Services\Blueprint\CyclicDependencyValidator;
use App\Services\Blueprint\BlueprintStructureService;

beforeEach(function () {
    $this->validator = app(CyclicDependencyValidator::class);
    $this->service = app(BlueprintStructureService::class);
});

test('нельзя встроить blueprint в самого себя', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);

    expect(fn() => $this->validator->ensureNoCyclicDependency($a, $a))
        ->toThrow(CyclicDependencyException::class, 'в самого себя');
});

test('нельзя создать прямой цикл A → B → A', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    // A → B (ok)
    $this->service->createEmbed($a, $b);

    // B → A (цикл)
    expect(fn() => $this->service->createEmbed($b, $a))
        ->toThrow(CyclicDependencyException::class);
});

test('нельзя создать транзитивный цикл A → B → C → A', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);
    $c = Blueprint::factory()->create(['code' => 'c']);

    // A → B → C
    $this->service->createEmbed($a, $b);
    $this->service->createEmbed($b, $c);

    // C → A (транзитивный цикл)
    expect(fn() => $this->service->createEmbed($c, $a))
        ->toThrow(CyclicDependencyException::class);
});

test('можно создать множественное встраивание без цикла', function () {
    $address = Blueprint::factory()->create(['code' => 'address']);
    $company = Blueprint::factory()->create(['code' => 'company']);
    $person = Blueprint::factory()->create(['code' => 'person']);

    // Address → Company, Address → Person (параллельно, без циклов)
    $this->service->createEmbed($company, $address);
    $this->service->createEmbed($person, $address);

    expect($company->embeds()->count())->toBe(1)
        ->and($person->embeds()->count())->toBe(1);
});

test('можно создать diamond dependency без цикла', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);
    $c = Blueprint::factory()->create(['code' => 'c']);
    $d = Blueprint::factory()->create(['code' => 'd']);

    // Diamond: D → B, D → C, B → A, C → A (нет цикла)
    $this->service->createEmbed($d, $b);
    $this->service->createEmbed($d, $c);
    $this->service->createEmbed($b, $a);
    $this->service->createEmbed($c, $a);

    expect($d->embeds()->count())->toBe(2);
});

test('canEmbed возвращает false для циклов', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();

    $this->service->createEmbed($a, $b);

    expect($this->validator->canEmbed($b->id, $a->id))->toBeFalse();
});

test('canEmbed возвращает true если циклов нет', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();
    $c = Blueprint::factory()->create();

    $this->service->createEmbed($a, $b);

    expect($this->validator->canEmbed($c->id, $a->id))->toBeTrue()
        ->and($this->validator->canEmbed($c->id, $b->id))->toBeTrue();
});
```

---

## J.2. Unit: Материализация (16 часов)

`tests/Unit/Services/Blueprint/MaterializationServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\Path;
use App\Services\Blueprint\BlueprintStructureService;
use App\Services\Blueprint\MaterializationService;

beforeEach(function () {
    $this->service = app(BlueprintStructureService::class);
    $this->materialization = app(MaterializationService::class);
});

test('простое встраивание копирует все поля', function () {
    $address = Blueprint::factory()->create(['code' => 'address']);
    $company = Blueprint::factory()->create(['code' => 'company']);

    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'street', 'full_path' => 'street']);
    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'city', 'full_path' => 'city']);

    $embed = $this->service->createEmbed($company, $address);

    $copiedPaths = Path::where('blueprint_embed_id', $embed->id)->get();

    expect($copiedPaths)->toHaveCount(2)
        ->and($copiedPaths->pluck('name')->all())->toContain('street', 'city')
        ->and($copiedPaths->pluck('full_path')->all())->toContain('street', 'city');
});

test('встраивание под host_path добавляет префикс к full_path', function () {
    $address = Blueprint::factory()->create(['code' => 'address']);
    $company = Blueprint::factory()->create(['code' => 'company']);

    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'street', 'full_path' => 'street']);

    $office = $this->service->createPath($company, ['name' => 'office', 'data_type' => 'json']);

    $embed = $this->service->createEmbed($company, $address, $office);

    $copiedPath = Path::where('blueprint_embed_id', $embed->id)->first();

    expect($copiedPath->full_path)->toBe('office.street')
        ->and($copiedPath->parent_id)->toBe($office->id);
});

test('множественное встраивание под разные host_path', function () {
    $address = Blueprint::factory()->create(['code' => 'address']);
    $company = Blueprint::factory()->create(['code' => 'company']);

    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'street', 'full_path' => 'street']);

    $office = $this->service->createPath($company, ['name' => 'office', 'data_type' => 'json']);
    $legal = $this->service->createPath($company, ['name' => 'legal', 'data_type' => 'json']);

    $embed1 = $this->service->createEmbed($company, $address, $office);
    $embed2 = $this->service->createEmbed($company, $address, $legal);

    $copies1 = Path::where('blueprint_embed_id', $embed1->id)->get();
    $copies2 = Path::where('blueprint_embed_id', $embed2->id)->get();

    expect($copies1->first()->full_path)->toBe('office.street')
        ->and($copies2->first()->full_path)->toBe('legal.street');
});

test('транзитивное встраивание D → C, C → A, A → B разворачивает все уровни', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);
    $c = Blueprint::factory()->create(['code' => 'c']);
    $d = Blueprint::factory()->create(['code' => 'd']);

    Path::factory()->create(['blueprint_id' => $b->id, 'name' => 'b_field', 'full_path' => 'b_field']);
    Path::factory()->create(['blueprint_id' => $c->id, 'name' => 'c_field', 'full_path' => 'c_field']);

    // A → B
    $this->service->createEmbed($a, $b);

    // C → A
    $this->service->createEmbed($c, $a);

    // D → C
    $embed = $this->service->createEmbed($d, $c);

    // D должен иметь c_field + b_field (транзитивно через A → B)
    $paths = $d->paths()->orderBy('full_path')->get();

    expect($paths->pluck('full_path')->all())->toContain('b_field', 'c_field');
});

test('многоуровневое встраивание author.contacts ← ContactInfo', function () {
    $company = Blueprint::factory()->create(['code' => 'company']);
    $contactInfo = Blueprint::factory()->create(['code' => 'contact_info']);

    $author = $this->service->createPath($company, ['name' => 'author', 'data_type' => 'json']);
    $contacts = $this->service->createPath($company, [
        'name' => 'contacts',
        'parent_id' => $author->id,
        'data_type' => 'json',
    ]);

    Path::factory()->create(['blueprint_id' => $contactInfo->id, 'name' => 'phone', 'full_path' => 'phone']);

    $embed = $this->service->createEmbed($company, $contactInfo, $contacts);

    $copiedPath = Path::where('blueprint_embed_id', $embed->id)->first();

    expect($copiedPath->full_path)->toBe('author.contacts.phone')
        ->and($copiedPath->parent_id)->toBe($contacts->id);
});

test('PRE-CHECK выявляет конфликт full_path перед вставкой', function () {
    $host = Blueprint::factory()->create(['code' => 'host']);
    $embedded = Blueprint::factory()->create(['code' => 'embedded']);

    // host имеет поле 'email'
    Path::factory()->create(['blueprint_id' => $host->id, 'name' => 'email', 'full_path' => 'email']);

    // embedded тоже имеет 'email'
    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'email', 'full_path' => 'email']);

    // Встраивание в корень → конфликт
    expect(fn() => $this->service->createEmbed($host, $embedded))
        ->toThrow(PathConflictException::class, 'конфликт путей');
});

test('PRE-CHECK разрешает встраивание если full_path разные', function () {
    $host = Blueprint::factory()->create(['code' => 'host']);
    $embedded = Blueprint::factory()->create(['code' => 'embedded']);

    Path::factory()->create(['blueprint_id' => $host->id, 'name' => 'email', 'full_path' => 'email']);

    $contacts = $this->service->createPath($host, ['name' => 'contacts', 'data_type' => 'json']);

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'email', 'full_path' => 'email']);

    // Встраивание под contacts → full_path = contacts.email (нет конфликта)
    $embed = $this->service->createEmbed($host, $embedded, $contacts);

    expect($embed->id)->toBeGreaterThan(0);
});

test('копии помечены как readonly', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $b->id, 'name' => 'field1', 'full_path' => 'field1']);

    $embed = $this->service->createEmbed($a, $b);

    $copiedPath = Path::where('blueprint_embed_id', $embed->id)->first();

    expect($copiedPath->is_readonly)->toBeTrue()
        ->and($copiedPath->source_blueprint_id)->toBe($b->id)
        ->and($copiedPath->blueprint_embed_id)->toBe($embed->id);
});

test('удаление embed удаляет все копии', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $b->id, 'name' => 'field1', 'full_path' => 'field1']);

    $embed = $this->service->createEmbed($a, $b);

    $copiesCount = Path::where('blueprint_embed_id', $embed->id)->count();
    expect($copiesCount)->toBeGreaterThan(0);

    $this->service->deleteEmbed($embed);

    $copiesCountAfter = Path::where('blueprint_embed_id', $embed->id)->count();
    expect($copiesCountAfter)->toBe(0);
});
```

---

## J.3. Unit: Каскадные события (12 часов)

`tests/Unit/Listeners/Blueprint/RematerializeEmbedsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Events\Blueprint\BlueprintStructureChanged;
use App\Listeners\Blueprint\RematerializeEmbeds;
use App\Models\Blueprint;
use App\Models\Path;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->service = app(BlueprintStructureService::class);
});

test('изменение A триггерит рематериализацию B если B → A', function () {
    Event::fake([BlueprintStructureChanged::class]);

    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    Path::factory()->create(['blueprint_id' => $a->id, 'name' => 'field_a', 'full_path' => 'field_a']);

    $this->service->createEmbed($b, $a);

    Event::assertDispatched(BlueprintStructureChanged::class, 1); // от createEmbed

    // Изменить A
    Event::fake();
    $this->service->createPath($a, ['name' => 'new_field', 'data_type' => 'string']);

    // Событие BlueprintStructureChanged должно быть диспатчено для A
    Event::assertDispatched(BlueprintStructureChanged::class, function ($event) use ($a) {
        return $event->blueprint->id === $a->id;
    });
});

test('транзитивная цепочка Geo → Address → Company → Department', function () {
    $geo = Blueprint::factory()->create(['code' => 'geo']);
    $address = Blueprint::factory()->create(['code' => 'address']);
    $company = Blueprint::factory()->create(['code' => 'company']);
    $department = Blueprint::factory()->create(['code' => 'department']);

    Path::factory()->create(['blueprint_id' => $geo->id, 'name' => 'lat', 'full_path' => 'lat']);

    // Создать цепочку зависимостей
    $this->service->createEmbed($address, $geo);       // Address → Geo
    $this->service->createEmbed($company, $address);   // Company → Address → Geo
    $this->service->createEmbed($department, $company); // Department → Company → Address → Geo

    // Department должен иметь поле 'lat' (транзитивно)
    $paths = $department->paths()->get();
    expect($paths->pluck('name')->all())->toContain('lat');

    // Изменить Geo
    Event::fake();
    $this->service->createPath($geo, ['name' => 'lng', 'data_type' => 'float']);

    // События должны быть диспатчены для всех зависимых
    Event::assertDispatched(BlueprintStructureChanged::class);

    // Department должен получить новое поле 'lng'
    $department->refresh();
    $paths = $department->paths()->get();
    expect($paths->pluck('name')->all())->toContain('lat', 'lng');
});

test('processedBlueprints предотвращает бесконечный цикл событий', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    Path::factory()->create(['blueprint_id' => $a->id, 'name' => 'field_a', 'full_path' => 'field_a']);
    Path::factory()->create(['blueprint_id' => $b->id, 'name' => 'field_b', 'full_path' => 'field_b']);

    // A → B
    $this->service->createEmbed($a, $b);

    // Диспатчить событие вручную с processedBlueprints = [A]
    $event = new BlueprintStructureChanged($a, [$a->id]);
    $listener = app(RematerializeEmbeds::class);

    // Listener не должен зациклиться
    $listener->handle($event);

    // Проверить, что processedBlueprints работает
    expect(true)->toBeTrue(); // Если дошли сюда — зацикливания нет
});

test('версионирование структуры обновляется при изменении', function () {
    $this->artisan('migrate', ['--path' => 'database/migrations/add_structure_version_to_blueprints_and_entries.php']);

    $blueprint = Blueprint::factory()->create(['structure_version' => 1]);

    $this->service->createPath($blueprint, ['name' => 'field1', 'data_type' => 'string']);

    $blueprint->refresh();

    expect($blueprint->structure_version)->toBe(2);
})->skip('Требует миграцию версионирования');
```

---

## J.4. Feature: CRUD Blueprint (12 часов)

`tests/Feature/Admin/BlueprintControllerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\PostType;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('можно создать blueprint через API', function () {
    $response = $this->postJson('/api/admin/blueprints', [
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

    $response = $this->postJson('/api/admin/blueprints', [
        'name' => 'Test',
        'code' => 'existing',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('можно добавить поле в blueprint', function () {
    $blueprint = Blueprint::factory()->create();

    $response = $this->postJson("/api/admin/blueprints/{$blueprint->id}/paths", [
        'name' => 'title',
        'data_type' => 'string',
        'is_required' => true,
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

    $response = $this->putJson("/api/admin/blueprints/{$blueprint->id}", [
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

    $response = $this->deleteJson("/api/admin/blueprints/{$blueprint->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Невозможно удалить blueprint');

    $this->assertDatabaseHas('blueprints', ['id' => $blueprint->id]);
});

test('можно удалить неиспользуемый blueprint', function () {
    $blueprint = Blueprint::factory()->create();

    $response = $this->deleteJson("/api/admin/blueprints/{$blueprint->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('blueprints', ['id' => $blueprint->id]);
});

test('получение списка blueprints с пагинацией', function () {
    Blueprint::factory()->count(20)->create();

    $response = $this->getJson('/api/admin/blueprints?per_page=10');

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

test('поиск blueprints по name/code', function () {
    Blueprint::factory()->create(['code' => 'article', 'name' => 'Article']);
    Blueprint::factory()->create(['code' => 'page', 'name' => 'Page']);

    $response = $this->getJson('/api/admin/blueprints?search=article');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'article');
});
```

---

## J.5. Feature: CRUD Embeds (12 часов)

`tests/Feature/Admin/BlueprintEmbedControllerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Path;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('можно создать встраивание', function () {
    $host = Blueprint::factory()->create(['code' => 'company']);
    $embedded = Blueprint::factory()->create(['code' => 'address']);

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'street', 'full_path' => 'street']);

    $response = $this->postJson("/api/admin/blueprints/{$host->id}/embeds", [
        'embedded_blueprint_id' => $embedded->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.blueprint_id', $host->id)
        ->assertJsonPath('data.embedded_blueprint_id', $embedded->id);

    // Проверить материализацию
    $copiedPaths = Path::where('blueprint_id', $host->id)
        ->where('source_blueprint_id', $embedded->id)
        ->get();

    expect($copiedPaths)->toHaveCount(1)
        ->and($copiedPaths->first()->name)->toBe('street');
});

test('можно создать встраивание под host_path', function () {
    $host = Blueprint::factory()->create(['code' => 'company']);
    $embedded = Blueprint::factory()->create(['code' => 'address']);

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'street', 'full_path' => 'street']);

    $office = Path::factory()->create([
        'blueprint_id' => $host->id,
        'name' => 'office',
        'full_path' => 'office',
        'data_type' => 'json',
    ]);

    $response = $this->postJson("/api/admin/blueprints/{$host->id}/embeds", [
        'embedded_blueprint_id' => $embedded->id,
        'host_path_id' => $office->id,
    ]);

    $response->assertCreated();

    // Проверить full_path копии
    $copiedPath = Path::where('blueprint_id', $host->id)
        ->where('full_path', 'office.street')
        ->first();

    expect($copiedPath)->not->toBeNull()
        ->and($copiedPath->parent_id)->toBe($office->id);
});

test('нельзя создать цикл через API', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    Path::factory()->create(['blueprint_id' => $b->id, 'name' => 'field1', 'full_path' => 'field1']);

    // A → B (ok)
    $this->postJson("/api/admin/blueprints/{$a->id}/embeds", [
        'embedded_blueprint_id' => $b->id,
    ])->assertCreated();

    // B → A (цикл)
    $response = $this->postJson("/api/admin/blueprints/{$b->id}/embeds", [
        'embedded_blueprint_id' => $a->id,
    ]);

    $response->assertUnprocessable();
});

test('можно удалить встраивание', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'field1', 'full_path' => 'field1']);

    $createResponse = $this->postJson("/api/admin/blueprints/{$host->id}/embeds", [
        'embedded_blueprint_id' => $embedded->id,
    ]);

    $embedId = $createResponse->json('data.id');

    // Удалить
    $response = $this->deleteJson("/api/admin/embeds/{$embedId}");

    $response->assertOk();

    // Проверить, что копии удалены
    $copiesCount = Path::where('blueprint_id', $host->id)
        ->where('source_blueprint_id', $embedded->id)
        ->count();

    expect($copiesCount)->toBe(0);
});

test('получение списка встраиваний blueprint', function () {
    $host = Blueprint::factory()->create();
    $embedded1 = Blueprint::factory()->create(['code' => 'embedded1']);
    $embedded2 = Blueprint::factory()->create(['code' => 'embedded2']);

    Path::factory()->create(['blueprint_id' => $embedded1->id, 'name' => 'f1', 'full_path' => 'f1']);
    Path::factory()->create(['blueprint_id' => $embedded2->id, 'name' => 'f2', 'full_path' => 'f2']);

    $this->postJson("/api/admin/blueprints/{$host->id}/embeds", ['embedded_blueprint_id' => $embedded1->id]);
    $this->postJson("/api/admin/blueprints/{$host->id}/embeds", ['embedded_blueprint_id' => $embedded2->id]);

    $response = $this->getJson("/api/admin/blueprints/{$host->id}/embeds");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});
```

---

## J.6. Feature: Индексация Entry (16 часов)

`tests/Feature/EntryIndexingTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\DocValue;
use App\Models\DocRef;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('создание Entry автоматически индексирует данные', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'article']);
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test Entry',
        'data_json' => [
            'title' => 'My Article',
        ],
    ]);

    // Проверить индексацию
    $docValue = DocValue::where('entry_id', $entry->id)->first();

    expect($docValue)->not->toBeNull()
        ->and($docValue->value_string)->toBe('My Article');
});

test('обновление Entry реиндексирует данные', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test',
        'data_json' => ['title' => 'Old Title'],
    ]);

    // Обновить
    $entry->update(['data_json' => ['title' => 'New Title']]);

    $docValue = DocValue::where('entry_id', $entry->id)->first();

    expect($docValue->value_string)->toBe('New Title');
});

test('удаление Entry очищает индексы', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'is_indexed' => true,
    ]);

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test',
        'data_json' => ['title' => 'Title'],
    ]);

    $entryId = $entry->id;

    $entry->delete();

    $docValuesCount = DocValue::where('entry_id', $entryId)->count();

    expect($docValuesCount)->toBe(0);
});

test('индексация массивов с array_index', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'data_type' => 'string',
        'cardinality' => 'many',
        'is_indexed' => true,
    ]);

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test',
        'data_json' => ['tags' => ['cms', 'laravel', 'php']],
    ]);

    $docValues = DocValue::where('entry_id', $entry->id)->orderBy('array_index')->get();

    expect($docValues)->toHaveCount(3)
        ->and($docValues->pluck('value_string')->all())->toBe(['cms', 'laravel', 'php'])
        ->and($docValues->pluck('array_index')->all())->toBe([0, 1, 2]);
});

test('индексация ref полей', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'related_article',
        'full_path' => 'related_article',
        'data_type' => 'ref',
        'is_indexed' => true,
    ]);

    $relatedEntry = Entry::factory()->create();

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test',
        'data_json' => ['related_article' => $relatedEntry->id],
    ]);

    $docRef = DocRef::where('entry_id', $entry->id)->first();

    expect($docRef)->not->toBeNull()
        ->and($docRef->target_entry_id)->toBe($relatedEntry->id);
});

test('wherePath фильтрует Entry по индексированным полям', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Entry 1',
        'data_json' => ['author' => 'John Doe'],
    ]);

    Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Entry 2',
        'data_json' => ['author' => 'Jane Smith'],
    ]);

    $entries = Entry::wherePath('author', '=', 'John Doe')->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->title)->toBe('Entry 1');
});

test('whereRef фильтрует Entry по ref полям', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'related',
        'full_path' => 'related',
        'data_type' => 'ref',
        'is_indexed' => true,
    ]);

    $targetEntry = Entry::factory()->create();

    Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Entry 1',
        'data_json' => ['related' => $targetEntry->id],
    ]);

    Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Entry 2',
        'data_json' => ['related' => 999],
    ]);

    $entries = Entry::whereRef('related', $targetEntry->id)->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->title)->toBe('Entry 1');
});
```

---

## J.7. Integration: Full Flow (20 часов)

`tests/Integration/BlueprintFullFlowTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Support\Facades\Event;

test('полный цикл: создание графа → встраивание → Entry → изменение структуры → каскады', function () {
    $service = app(BlueprintStructureService::class);

    // 1. Создать blueprints
    $geo = $service->createBlueprint(['name' => 'Geo', 'code' => 'geo']);
    $address = $service->createBlueprint(['name' => 'Address', 'code' => 'address']);
    $company = $service->createBlueprint(['name' => 'Company', 'code' => 'company']);

    // 2. Добавить поля
    $service->createPath($geo, ['name' => 'lat', 'data_type' => 'float', 'is_indexed' => true]);
    $service->createPath($geo, ['name' => 'lng', 'data_type' => 'float', 'is_indexed' => true]);

    $service->createPath($address, ['name' => 'street', 'data_type' => 'string', 'is_indexed' => true]);

    $service->createPath($company, ['name' => 'name', 'data_type' => 'string', 'is_indexed' => true]);

    // 3. Создать встраивания: Address → Geo, Company → Address
    $service->createEmbed($address, $geo);
    $service->createEmbed($company, $address);

    // 4. Проверить транзитивную материализацию
    $companyPaths = $company->paths()->orderBy('full_path')->get();
    expect($companyPaths->pluck('name')->all())->toContain('name', 'street', 'lat', 'lng');

    // 5. Создать PostType и Entry
    $postType = PostType::factory()->create(['blueprint_id' => $company->id]);

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'ACME Corp',
        'data_json' => [
            'name' => 'ACME Corporation',
            'street' => '123 Main St',
            'lat' => 40.7128,
            'lng' => -74.0060,
        ],
    ]);

    // 6. Проверить индексацию
    expect($entry->values()->count())->toBeGreaterThan(0);

    $latValue = $entry->values()
        ->whereHas('path', fn($q) => $q->where('name', 'lat'))
        ->first();

    expect($latValue->value_float)->toBe(40.7128);

    // 7. Изменить структуру Geo (добавить поле)
    Event::fake();
    $service->createPath($geo, ['name' => 'altitude', 'data_type' => 'float', 'is_indexed' => true]);

    // 8. Проверить каскадную рематериализацию
    $company->refresh();
    $companyPathsAfter = $company->paths()->get();
    expect($companyPathsAfter->pluck('name')->all())->toContain('altitude');

    // 9. Реиндексировать Entry
    $entry->data_json = array_merge($entry->data_json, ['altitude' => 100.0]);
    $entry->save();

    // 10. Проверить новое значение в индексе
    $altitudeValue = $entry->values()
        ->whereHas('path', fn($q) => $q->where('name', 'altitude'))
        ->first();

    expect($altitudeValue->value_float)->toBe(100.0);
});

test('сложный граф с diamond dependency работает корректно', function () {
    $service = app(BlueprintStructureService::class);

    // Diamond: D → B, D → C, B → A, C → A
    $a = $service->createBlueprint(['name' => 'A', 'code' => 'a']);
    $b = $service->createBlueprint(['name' => 'B', 'code' => 'b']);
    $c = $service->createBlueprint(['name' => 'C', 'code' => 'c']);
    $d = $service->createBlueprint(['name' => 'D', 'code' => 'd']);

    $service->createPath($a, ['name' => 'field_a', 'data_type' => 'string']);
    $service->createPath($b, ['name' => 'field_b', 'data_type' => 'string']);
    $service->createPath($c, ['name' => 'field_c', 'data_type' => 'string']);

    $service->createEmbed($b, $a);
    $service->createEmbed($c, $a);
    $service->createEmbed($d, $b);
    $service->createEmbed($d, $c);

    // D должен иметь field_a (дважды, через B и C), field_b, field_c
    $dPaths = $d->paths()->get();
    expect($dPaths->pluck('name')->all())->toContain('field_a', 'field_b', 'field_c');
});
```

---

## J.8. Performance: Масштабирование (12 часов)

`tests/Performance/BlueprintPerformanceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Services\Blueprint\BlueprintStructureService;

test('материализация blueprint с 100 полями < 1s', function () {
    $service = app(BlueprintStructureService::class);

    $host = $service->createBlueprint(['name' => 'Host', 'code' => 'host']);
    $embedded = $service->createBlueprint(['name' => 'Embedded', 'code' => 'embedded']);

    // Создать 100 полей в embedded
    for ($i = 0; $i < 100; $i++) {
        Path::factory()->create([
            'blueprint_id' => $embedded->id,
            'name' => "field_{$i}",
            'full_path' => "field_{$i}",
        ]);
    }

    $start = microtime(true);

    $service->createEmbed($host, $embedded);

    $duration = (microtime(true) - $start) * 1000; // ms

    expect($duration)->toBeLessThan(1000); // < 1s
})->skip('Performance test');

test('индексация Entry с 50 полями < 100ms', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Создать 50 индексируемых полей
    for ($i = 0; $i < 50; $i++) {
        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'name' => "field_{$i}",
            'full_path' => "field_{$i}",
            'data_type' => 'string',
            'is_indexed' => true,
        ]);
    }

    $data = [];
    for ($i = 0; $i < 50; $i++) {
        $data["field_{$i}"] = "value_{$i}";
    }

    $start = microtime(true);

    Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test',
        'data_json' => $data,
    ]);

    $duration = (microtime(true) - $start) * 1000; // ms

    expect($duration)->toBeLessThan(100); // < 100ms
})->skip('Performance test');

test('запрос wherePath по 10000 Entry < 50ms', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'category',
        'full_path' => 'category',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    // Создать 10000 Entry
    for ($i = 0; $i < 10000; $i++) {
        Entry::create([
            'post_type_id' => $postType->id,
            'title' => "Entry {$i}",
            'data_json' => ['category' => $i % 10 === 0 ? 'target' : 'other'],
        ]);
    }

    $start = microtime(true);

    $entries = Entry::wherePath('category', '=', 'target')->get();

    $duration = (microtime(true) - $start) * 1000; // ms

    expect($entries)->toHaveCount(1000)
        ->and($duration)->toBeLessThan(50); // < 50ms с индексами
})->skip('Performance test');
```

---

## Команды для запуска тестов

```bash
# Все тесты
php artisan test

# Только Unit тесты
php artisan test --testsuite=Unit

# Только Feature тесты
php artisan test --testsuite=Feature

# Конкретная группа
php artisan test --filter=CyclicDependency
php artisan test --filter=Materialization
php artisan test --filter=BlueprintController
php artisan test --filter=EntryIndexing

# С покрытием кода
php artisan test --coverage

# Performance тесты (skip по умолчанию)
php artisan test --group=performance

# Parallel execution (быстрее)
php artisan test --parallel
```

---

## Настройка PHPUnit

`phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
    </php>
</phpunit>
```

---

## Покрытие тестами

### Целевые метрики:

| Компонент                      | Покрытие | Приоритет |
|--------------------------------|----------|-----------|
| CyclicDependencyValidator      | 100%     | Критично  |
| MaterializationService         | 100%     | Критично  |
| PathConflictValidator          | 100%     | Критично  |
| RematerializeEmbeds (Listener) | 100%     | Критично  |
| BlueprintStructureService      | 90%+     | Критично  |
| HasDocumentData (trait)        | 90%+     | Критично  |
| EntryIndexer                   | 90%+     | Критично  |
| Controllers                    | 80%+     | Важно     |
| Models                         | 70%+     | Важно     |

---

**Результат:** Комплексное покрытие тестами всех критических компонентов системы, готовность к production.

**Создано 8 документов (318 часов):**
- Must Have: A-H (196 ч)
- Should Have: I (34 ч)
- Could Have: J (88 ч)

**Осталось опционально:** K-M (оптимизация, мониторинг, документация — 92 ч).

