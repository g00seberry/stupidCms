<?php

declare(strict_types=1);

use App\Exceptions\Blueprint\MaxDepthExceededException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use App\Services\Blueprint\MaterializationService;

beforeEach(function () {
    $this->service = app(MaterializationService::class);
});

test('простое встраивание создаёт копии полей', function () {
    $host = Blueprint::factory()->create(['code' => 'host']);
    $embedded = Blueprint::factory()->create(['code' => 'embedded']);

    // Embedded поля
    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'field1',
        'full_path' => 'field1',
    ]);

    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'field2',
        'full_path' => 'field2',
    ]);

    // Создаём embed
    $embed = BlueprintEmbed::create([
        'blueprint_id' => $host->id,
        'embedded_blueprint_id' => $embedded->id,
        'host_path_id' => null, // в корень
    ]);

    // Материализуем
    $this->service->materialize($embed);

    // Проверяем копии
    $copies = Path::where('blueprint_id', $host->id)
        ->where('blueprint_embed_id', $embed->id)
        ->get();

    expect($copies)->toHaveCount(2)
        ->and($copies->pluck('name')->all())->toContain('field1', 'field2')
        ->and($copies->pluck('full_path')->all())->toContain('field1', 'field2')
        ->and($copies->every(fn($p) => $p->is_readonly))->toBeTrue()
        ->and($copies->every(fn($p) => $p->source_blueprint_id === $embedded->id))->toBeTrue();
});

test('встраивание под host_path создаёт вложенные пути', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    $hostPath = Path::factory()->create([
        'blueprint_id' => $host->id,
        'name' => 'author',
        'full_path' => 'author',
    ]);

    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'name',
        'full_path' => 'name',
    ]);

    $embed = BlueprintEmbed::create([
        'blueprint_id' => $host->id,
        'embedded_blueprint_id' => $embedded->id,
        'host_path_id' => $hostPath->id,
    ]);

    $this->service->materialize($embed);

    $copy = Path::where('blueprint_id', $host->id)
        ->where('blueprint_embed_id', $embed->id)
        ->where('name', 'name')
        ->first();

    expect($copy)->not->toBeNull()
        ->and($copy->full_path)->toBe('author.name')
        ->and($copy->parent_id)->toBe($hostPath->id);
});

test('транзитивное встраивание D → C → A → B', function () {
    // Blueprint D
    $d = Blueprint::factory()->create(['code' => 'd']);
    Path::factory()->create(['blueprint_id' => $d->id, 'name' => 'field_d', 'full_path' => 'field_d']);

    // Blueprint C + embed D
    $c = Blueprint::factory()->create(['code' => 'c']);
    $groupD = Path::factory()->create([
        'blueprint_id' => $c->id,
        'name' => 'group_d',
        'full_path' => 'group_d',
    ]);
    $embedCD = BlueprintEmbed::create([
        'blueprint_id' => $c->id,
        'embedded_blueprint_id' => $d->id,
        'host_path_id' => $groupD->id,
    ]);
    $this->service->materialize($embedCD);

    // Blueprint A + embed C
    $a = Blueprint::factory()->create(['code' => 'a']);
    $groupC = Path::factory()->create([
        'blueprint_id' => $a->id,
        'name' => 'group_c',
        'full_path' => 'group_c',
    ]);
    $embedAC = BlueprintEmbed::create([
        'blueprint_id' => $a->id,
        'embedded_blueprint_id' => $c->id,
        'host_path_id' => $groupC->id,
    ]);
    $this->service->materialize($embedAC);

    // Blueprint B + embed A
    $b = Blueprint::factory()->create(['code' => 'b']);
    $groupA = Path::factory()->create([
        'blueprint_id' => $b->id,
        'name' => 'group_a',
        'full_path' => 'group_a',
    ]);
    $embedBA = BlueprintEmbed::create([
        'blueprint_id' => $b->id,
        'embedded_blueprint_id' => $a->id,
        'host_path_id' => $groupA->id,
    ]);
    $this->service->materialize($embedBA);

    // Проверяем транзитивное поле из D
    $transitiveField = Path::where('blueprint_id', $b->id)
        ->where('full_path', 'group_a.group_c.group_d.field_d')
        ->first();

    expect($transitiveField)->not->toBeNull()
        ->and($transitiveField->source_blueprint_id)->toBe($d->id)
        ->and($transitiveField->blueprint_embed_id)->toBe($embedBA->id); // корневой embed B→A
});

test('множественное встраивание Address в Company', function () {
    $company = Blueprint::factory()->create(['code' => 'company']);
    $address = Blueprint::factory()->create(['code' => 'address']);

    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'street', 'full_path' => 'street']);
    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'city', 'full_path' => 'city']);

    $office = Path::factory()->create(['blueprint_id' => $company->id, 'name' => 'office', 'full_path' => 'office']);
    $legal = Path::factory()->create(['blueprint_id' => $company->id, 'name' => 'legal', 'full_path' => 'legal']);

    // Два embed'а одного blueprint
    $embed1 = BlueprintEmbed::create([
        'blueprint_id' => $company->id,
        'embedded_blueprint_id' => $address->id,
        'host_path_id' => $office->id,
    ]);

    $embed2 = BlueprintEmbed::create([
        'blueprint_id' => $company->id,
        'embedded_blueprint_id' => $address->id,
        'host_path_id' => $legal->id,
    ]);

    $this->service->materialize($embed1);
    $this->service->materialize($embed2);

    // Проверяем раздельные копии
    $officePaths = Path::where('blueprint_embed_id', $embed1->id)->pluck('full_path')->all();
    $legalPaths = Path::where('blueprint_embed_id', $embed2->id)->pluck('full_path')->all();

    expect($officePaths)->toContain('office.street', 'office.city')
        ->and($legalPaths)->toContain('legal.street', 'legal.city');
});

test('рематериализация удаляет старые копии', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'field1', 'full_path' => 'field1']);

    $embed = BlueprintEmbed::create([
        'blueprint_id' => $host->id,
        'embedded_blueprint_id' => $embedded->id,
    ]);

    // Первая материализация
    $this->service->materialize($embed);
    $countBefore = Path::where('blueprint_embed_id', $embed->id)->count();

    // Добавляем новое поле в embedded
    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'field2', 'full_path' => 'field2']);

    // Рематериализация
    $this->service->materialize($embed);
    $countAfter = Path::where('blueprint_embed_id', $embed->id)->count();

    expect($countAfter)->toBe(2) // field1 + field2
        ->and($countBefore)->toBe(1);
});

test('превышение максимальной глубины выбрасывает исключение', function () {
    // Создать цепочку длиннее MAX_EMBED_DEPTH (5)
    // bp0 → bp1 → bp2 → bp3 → bp4 → bp5 → bp6 (глубина 6 > MAX_EMBED_DEPTH 5)
    $blueprints = collect(range(0, 6))->map(fn($i) => Blueprint::factory()->create(['code' => "bp$i"]));

    // Создаём все blueprint'ы с полями и группами
    $embeds = [];
    foreach ($blueprints as $i => $bp) {
        Path::factory()->create([
            'blueprint_id' => $bp->id,
            'name' => "field$i",
            'full_path' => "field$i",
        ]);

        if ($i < $blueprints->count() - 1) {
            $group = Path::factory()->create([
                'blueprint_id' => $bp->id,
                'name' => "group$i",
                'full_path' => "group$i",
            ]);

            // Создаём embed'ы, но не материализуем их
            $embeds[$i] = BlueprintEmbed::create([
                'blueprint_id' => $bp->id,
                'embedded_blueprint_id' => $blueprints[$i + 1]->id,
                'host_path_id' => $group->id,
            ]);
        }
    }

    // Материализуем root embed - это должно вызвать превышение глубины
    // при рекурсивном разворачивании цепочки bp0 → bp1 → ... → bp6
    expect(fn() => $this->service->materialize($embeds[0]))
        ->toThrow(MaxDepthExceededException::class);
});

