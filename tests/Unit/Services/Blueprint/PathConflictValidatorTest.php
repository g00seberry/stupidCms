<?php

declare(strict_types=1);

use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\Path;
use App\Services\Blueprint\PathConflictValidator;

beforeEach(function () {
    $this->validator = app(PathConflictValidator::class);
});

test('конфликт путей выбрасывает исключение', function () {
    $host = Blueprint::factory()->create(['code' => 'host']);
    $embedded = Blueprint::factory()->create(['code' => 'embedded']);

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
    expect(fn() => $this->validator->validateNoConflicts($embedded, $host, null))
        ->toThrow(PathConflictException::class, "конфликт путей: 'email'");
});

test('конфликт с вложенным путём', function () {
    $host = Blueprint::factory()->create(['code' => 'host']);
    $embedded = Blueprint::factory()->create(['code' => 'embedded']);

    // host имеет meta.created_at
    $meta = Path::factory()->create([
        'blueprint_id' => $host->id,
        'name' => 'meta',
        'full_path' => 'meta',
    ]);

    Path::factory()->create([
        'blueprint_id' => $host->id,
        'parent_id' => $meta->id,
        'name' => 'created_at',
        'full_path' => 'meta.created_at',
    ]);

    // embedded имеет created_at
    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'created_at',
        'full_path' => 'created_at',
    ]);

    // Встраиваем embedded под meta → конфликт meta.created_at
    expect(fn() => $this->validator->validateNoConflicts($embedded, $host, 'meta'))
        ->toThrow(PathConflictException::class, "meta.created_at");
});

test('нет конфликта при встраивании под разными базовыми путями', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create([
        'blueprint_id' => $host->id,
        'full_path' => 'office.address',
    ]);

    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'full_path' => 'address',
    ]);

    // Встраиваем под 'legal' → legal.address ≠ office.address
    expect(fn() => $this->validator->validateNoConflicts($embedded, $host, 'legal'))
        ->not->toThrow(PathConflictException::class);
});

test('транзитивные пути проверяются', function () {
    $materializationService = app(\App\Services\Blueprint\MaterializationService::class);

    // Blueprint C
    $c = Blueprint::factory()->create(['code' => 'c']);
    Path::factory()->create(['blueprint_id' => $c->id, 'name' => 'field_c', 'full_path' => 'field_c']);

    // Blueprint A встраивает C
    $a = Blueprint::factory()->create(['code' => 'a']);
    $groupC = Path::factory()->create([
        'blueprint_id' => $a->id,
        'name' => 'group_c',
        'full_path' => 'group_c',
    ]);

    $embedAC = \App\Models\BlueprintEmbed::create([
        'blueprint_id' => $a->id,
        'embedded_blueprint_id' => $c->id,
        'host_path_id' => $groupC->id,
    ]);
    
    // Материализуем C в A, чтобы Path'ы появились
    $materializationService->materialize($embedAC);

    // Blueprint B уже имеет author.group_c.field_c
    $b = Blueprint::factory()->create(['code' => 'b']);
    $author = Path::factory()->create(['blueprint_id' => $b->id, 'name' => 'author', 'full_path' => 'author']);
    $groupCinB = Path::factory()->create([
        'blueprint_id' => $b->id,
        'parent_id' => $author->id,
        'name' => 'group_c',
        'full_path' => 'author.group_c',
    ]);
    Path::factory()->create([
        'blueprint_id' => $b->id,
        'parent_id' => $groupCinB->id,
        'name' => 'field_c',
        'full_path' => 'author.group_c.field_c',
    ]);

    // Попытка встроить A под 'author' → конфликт транзитивного пути
    expect(fn() => $this->validator->validateNoConflicts($a, $b, 'author'))
        ->toThrow(PathConflictException::class, "author.group_c.field_c");
});

