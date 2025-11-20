<?php

declare(strict_types=1);

use App\Exceptions\Blueprint\CyclicDependencyException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Services\Blueprint\CyclicDependencyValidator;

beforeEach(function () {
    $this->validator = app(CyclicDependencyValidator::class);
});

test('запрет встраивания в самого себя', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'person']);

    expect(fn() => $this->validator->ensureNoCyclicDependency($blueprint, $blueprint))
        ->toThrow(CyclicDependencyException::class, "Нельзя встроить blueprint 'person' в самого себя");
});

test('запрет прямого цикла A → B → A', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    // Создаём A → B
    BlueprintEmbed::create([
        'blueprint_id' => $a->id,
        'embedded_blueprint_id' => $b->id,
    ]);

    // Попытка B → A должна провалиться
    expect(fn() => $this->validator->ensureNoCyclicDependency($b, $a))
        ->toThrow(CyclicDependencyException::class, "Циклическая зависимость");
});

test('запрет транзитивного цикла A → B → C → A', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);
    $c = Blueprint::factory()->create(['code' => 'c']);

    BlueprintEmbed::create(['blueprint_id' => $a->id, 'embedded_blueprint_id' => $b->id]);
    BlueprintEmbed::create(['blueprint_id' => $b->id, 'embedded_blueprint_id' => $c->id]);

    expect(fn() => $this->validator->ensureNoCyclicDependency($c, $a))
        ->toThrow(CyclicDependencyException::class);
});

test('разрешено множественное встраивание без цикла', function () {
    $address = Blueprint::factory()->create(['code' => 'address']);
    $company = Blueprint::factory()->create(['code' => 'company']);

    // Company → Address дважды (под разными host_path)
    BlueprintEmbed::create([
        'blueprint_id' => $company->id,
        'embedded_blueprint_id' => $address->id,
        'host_path_id' => null,
    ]);

    // Второй embed должен пройти валидацию
    expect(fn() => $this->validator->ensureNoCyclicDependency($company, $address))
        ->not->toThrow(CyclicDependencyException::class);
});

test('canEmbed возвращает false для самовстраивания', function () {
    $blueprint = Blueprint::factory()->create();

    expect($this->validator->canEmbed($blueprint->id, $blueprint->id))->toBeFalse();
});

test('canEmbed возвращает true для допустимого встраивания', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();

    expect($this->validator->canEmbed($a->id, $b->id))->toBeTrue();
});

test('canEmbed возвращает false для циклической зависимости', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();

    BlueprintEmbed::create([
        'blueprint_id' => $a->id,
        'embedded_blueprint_id' => $b->id,
    ]);

    expect($this->validator->canEmbed($b->id, $a->id))->toBeFalse();
});

