<?php

declare(strict_types=1);

use App\Exceptions\Blueprint\CyclicDependencyException;
use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use App\Models\PostType;
use App\Services\Blueprint\BlueprintStructureService;

beforeEach(function () {
    $this->service = app(BlueprintStructureService::class);
});

test('createBlueprint создаёт blueprint', function () {
    $blueprint = $this->service->createBlueprint([
        'name' => 'Test Blueprint',
        'code' => 'test_bp',
        'description' => 'Test description',
    ]);

    expect($blueprint)->toBeInstanceOf(Blueprint::class)
        ->and($blueprint->code)->toBe('test_bp')
        ->and($blueprint->name)->toBe('Test Blueprint');
});

test('createPath создаёт поле с корректным full_path', function () {
    $blueprint = Blueprint::factory()->create();

    $path = $this->service->createPath($blueprint, [
        'name' => 'title',
        'data_type' => 'string',
    ]);

    expect($path->full_path)->toBe('title')
        ->and($path->blueprint_id)->toBe($blueprint->id);
});

test('createPath вычисляет full_path для вложенных полей', function () {
    $blueprint = Blueprint::factory()->create();

    $parent = $this->service->createPath($blueprint, [
        'name' => 'author',
        'data_type' => 'json',
    ]);

    $child = $this->service->createPath($blueprint, [
        'name' => 'name',
        'parent_id' => $parent->id,
        'data_type' => 'string',
    ]);

    expect($child->full_path)->toBe('author.name')
        ->and($child->parent_id)->toBe($parent->id);
});

test('updatePath пересчитывает full_path при изменении name', function () {
    $blueprint = Blueprint::factory()->create();

    $parent = $this->service->createPath($blueprint, [
        'name' => 'author',
        'data_type' => 'json',
    ]);

    $child = $this->service->createPath($blueprint, [
        'name' => 'name',
        'parent_id' => $parent->id,
        'data_type' => 'string',
    ]);

    // Изменить parent
    $this->service->updatePath($parent, ['name' => 'writer']);

    $parent->refresh();
    $child->refresh();

    expect($parent->full_path)->toBe('writer')
        ->and($child->full_path)->toBe('writer.name');
});

test('updatePath запрещает редактирование скопированных полей', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'field1',
        'full_path' => 'field1',
    ]);

    $embed = $this->service->createEmbed($host, $embedded);

    $copiedPath = Path::where('blueprint_embed_id', $embed->id)->first();

    expect(fn() => $this->service->updatePath($copiedPath, ['name' => 'updated']))
        ->toThrow(\LogicException::class, 'Невозможно редактировать скопированное поле');
});

test('deletePath запрещает удаление скопированных полей', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'field1',
        'full_path' => 'field1',
    ]);

    $embed = $this->service->createEmbed($host, $embedded);

    $copiedPath = Path::where('blueprint_embed_id', $embed->id)->first();

    expect(fn() => $this->service->deletePath($copiedPath))
        ->toThrow(\LogicException::class, 'Невозможно удалить скопированное поле');
});

test('createEmbed проверяет циклы', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    // A → B
    $this->service->createEmbed($a, $b);

    // B → A должно провалиться (цикл)
    expect(fn() => $this->service->createEmbed($b, $a))
        ->toThrow(CyclicDependencyException::class);
});

test('createEmbed проверяет конфликты путей', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    // host уже имеет поле 'email'
    Path::factory()->create([
        'blueprint_id' => $host->id,
        'name' => 'email',
        'full_path' => 'email',
    ]);

    // embedded тоже имеет 'email'
    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'email',
        'full_path' => 'email',
    ]);

    // Встраивание в корень → конфликт
    expect(fn() => $this->service->createEmbed($host, $embedded))
        ->toThrow(PathConflictException::class);
});

test('createEmbed запрещает дублирование', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'f1', 'full_path' => 'f1']);

    // Первое встраивание
    $this->service->createEmbed($host, $embedded);

    // Второе встраивание в корень → дубликат
    expect(fn() => $this->service->createEmbed($host, $embedded))
        ->toThrow(\LogicException::class, 'уже встроен');
});

test('createEmbed разрешает множественное встраивание под разными host_path', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'f1', 'full_path' => 'f1']);

    $office = $this->service->createPath($host, ['name' => 'office', 'data_type' => 'json']);
    $legal = $this->service->createPath($host, ['name' => 'legal', 'data_type' => 'json']);

    $embed1 = $this->service->createEmbed($host, $embedded, $office);
    $embed2 = $this->service->createEmbed($host, $embedded, $legal);

    expect($embed1->id)->not->toBe($embed2->id);
});

test('deleteEmbed удаляет встраивание и копии', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'field1', 'full_path' => 'field1']);

    $embed = $this->service->createEmbed($host, $embedded);

    $copiesCount = Path::where('blueprint_embed_id', $embed->id)->count();
    expect($copiesCount)->toBeGreaterThan(0);

    $this->service->deleteEmbed($embed);

    expect(BlueprintEmbed::find($embed->id))->toBeNull()
        ->and(Path::where('blueprint_embed_id', $embed->id)->count())->toBe(0);
});

test('deleteBlueprint запрещает удаление используемого в PostType', function () {
    $blueprint = Blueprint::factory()->create();
    PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    expect(fn() => $this->service->deleteBlueprint($blueprint))
        ->toThrow(\LogicException::class, 'используется в PostType');
});

test('deleteBlueprint запрещает удаление встроенного blueprint', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'f1', 'full_path' => 'f1']);

    $this->service->createEmbed($host, $embedded);

    expect(fn() => $this->service->deleteBlueprint($embedded))
        ->toThrow(\LogicException::class, 'встроен в другие blueprint');
});

test('getEmbeddableBlueprintsFor исключает циклы', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);
    $c = Blueprint::factory()->create(['code' => 'c']);

    // A → B
    $this->service->createEmbed($a, $b);

    $embeddable = $this->service->getEmbeddableBlueprintsFor($a);

    // Можно встроить C (нет цикла)
    expect($embeddable->pluck('id')->all())->toContain($c->id);

    // Нельзя встроить A в B (создаст цикл B → A → B)
    $embeddableForB = $this->service->getEmbeddableBlueprintsFor($b);
    expect($embeddableForB->pluck('id')->all())->not->toContain($a->id);
});

test('canDeleteBlueprint возвращает причины невозможности удаления', function () {
    $blueprint = Blueprint::factory()->create();
    PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    $result = $this->service->canDeleteBlueprint($blueprint);

    expect($result['can_delete'])->toBeFalse()
        ->and($result['reasons'])->toContain('Используется в 1 PostType');
});

