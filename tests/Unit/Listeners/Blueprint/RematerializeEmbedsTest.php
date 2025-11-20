<?php

declare(strict_types=1);

use App\Events\Blueprint\BlueprintStructureChanged;
use App\Listeners\Blueprint\RematerializeEmbeds;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Support\Facades\Event;

test('изменение blueprint триггерит рематериализацию зависимых', function () {
    Event::fake();

    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    Path::factory()->create(['blueprint_id' => $a->id, 'name' => 'field_a', 'full_path' => 'field_a']);

    $embed = BlueprintEmbed::create([
        'blueprint_id' => $b->id,
        'embedded_blueprint_id' => $a->id,
    ]);

    // Материализуем первый раз
    app(\App\Services\Blueprint\MaterializationService::class)->materialize($embed);

    // Вызываем listener напрямую
    $event = new BlueprintStructureChanged($a);
    app(RematerializeEmbeds::class)->handle($event);

    // Проверяем, что событие триггерилось для B
    Event::assertDispatched(BlueprintStructureChanged::class, function ($event) use ($b) {
        return $event->blueprint->id === $b->id;
    });
});

test('транзитивная рематериализация C → B → A', function () {
    $c = Blueprint::factory()->create(['code' => 'c']);
    $b = Blueprint::factory()->create(['code' => 'b']);
    $a = Blueprint::factory()->create(['code' => 'a']);

    Path::factory()->create(['blueprint_id' => $c->id, 'name' => 'field_c', 'full_path' => 'field_c']);

    // B → C
    $embedBC = BlueprintEmbed::create([
        'blueprint_id' => $b->id,
        'embedded_blueprint_id' => $c->id,
    ]);

    // A → B
    $embedAB = BlueprintEmbed::create([
        'blueprint_id' => $a->id,
        'embedded_blueprint_id' => $b->id,
    ]);

    $service = app(\App\Services\Blueprint\MaterializationService::class);
    $service->materialize($embedBC);
    $service->materialize($embedAB);

    // Проверяем, что поля C скопированы в B
    $bCopy = Path::where('blueprint_id', $b->id)
        ->where('name', 'field_c')
        ->first();
    expect($bCopy)->not->toBeNull();

    // Добавляем новое поле в C
    Path::factory()->create(['blueprint_id' => $c->id, 'name' => 'new_field', 'full_path' => 'new_field']);

    // Вызываем listener для C - он должен каскадно обновить B и A
    $event = new BlueprintStructureChanged($c);
    app(RematerializeEmbeds::class)->handle($event);

    // Проверяем, что новое поле появилось в B (рематериализация B → C)
    $bNewField = Path::where('blueprint_id', $b->id)
        ->where('name', 'new_field')
        ->where('blueprint_embed_id', $embedBC->id)
        ->exists();
    expect($bNewField)->toBeTrue();

    // Проверяем, что новое поле появилось в A (рематериализация A → B)
    $aNewField = Path::where('blueprint_id', $a->id)
        ->where('name', 'new_field')
        ->where('blueprint_embed_id', $embedAB->id)
        ->exists();
    expect($aNewField)->toBeTrue();
});

test('защита от зацикливания processedBlueprints', function () {
    Event::fake();

    $a = Blueprint::factory()->create(['code' => 'a']);

    // Создаём событие с A уже в processedBlueprints
    $event = new BlueprintStructureChanged($a, [$a->id]);

    expect($event->wasProcessed($a->id))->toBeTrue();

    // Listener должен пропустить обработку
    $listener = app(RematerializeEmbeds::class);
    $listener->handle($event);

    // Событие не должно триггериться снова
    Event::assertNotDispatched(BlueprintStructureChanged::class);
});

test('множественное встраивание: оба embed рематериализуются', function () {
    // Не используем Event::fake(), чтобы listener реально обработал событие
    $address = Blueprint::factory()->create(['code' => 'address']);
    $company = Blueprint::factory()->create(['code' => 'company']);

    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'street', 'full_path' => 'street']);

    $office = Path::factory()->create(['blueprint_id' => $company->id, 'name' => 'office', 'full_path' => 'office']);
    $legal = Path::factory()->create(['blueprint_id' => $company->id, 'name' => 'legal', 'full_path' => 'legal']);

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

    $service = app(\App\Services\Blueprint\MaterializationService::class);
    $service->materialize($embed1);
    $service->materialize($embed2);

    // Добавляем новое поле в Address
    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'city', 'full_path' => 'city']);

    // Изменяем Address - listener должен рематериализовать оба embed
    event(new BlueprintStructureChanged($address));

    // Проверяем, что оба embed рематериализовались
    $officeCopy = Path::where('blueprint_embed_id', $embed1->id)
        ->where('name', 'city')
        ->exists();

    $legalCopy = Path::where('blueprint_embed_id', $embed2->id)
        ->where('name', 'city')
        ->exists();

    expect($officeCopy)->toBeTrue()
        ->and($legalCopy)->toBeTrue();
});

