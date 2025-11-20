<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Exceptions\Blueprint\MaxDepthExceededException;
use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Support\Facades\DB;

/**
 * Сервис рекурсивной материализации встраиваний.
 *
 * Копирует структуру embedded blueprint в host blueprint,
 * включая все транзитивные встраивания.
 */
class MaterializationService
{
    /**
     * Максимальная глубина вложенности встраиваний.
     */
    private const MAX_EMBED_DEPTH = 5;

    /**
     * @param PathConflictValidator $conflictValidator
     */
    public function __construct(
        private readonly PathConflictValidator $conflictValidator
    ) {}

    /**
     * Материализовать встраивание со всеми транзитивными зависимостями.
     *
     * Синхронная операция в рамках DB::transaction.
     *
     * @param BlueprintEmbed $embed Встраивание для материализации
     * @return void
     * @throws PathConflictException
     * @throws MaxDepthExceededException
     */
    public function materialize(BlueprintEmbed $embed): void
    {
        // Загрузить связи для работы
        $embed->load(['blueprint', 'embeddedBlueprint', 'hostPath']);
        
        $hostBlueprint = $embed->blueprint;
        $embeddedBlueprint = $embed->embeddedBlueprint;
        $hostPath = $embed->hostPath;

        DB::transaction(function () use ($embed, $hostBlueprint, $embeddedBlueprint, $hostPath) {
            $baseParentId = $hostPath?->id;
            $baseParentPath = $hostPath?->full_path;

            // 1. PRE-CHECK: проверка конфликтов full_path
            // Передаём ID embed для исключения его копий при рематериализации
            $this->conflictValidator->validateNoConflicts(
                $embeddedBlueprint,
                $hostBlueprint,
                $baseParentPath,
                $embed->id
            );

            // 2. Удалить старые копии (включая транзитивные)
            Path::where('blueprint_embed_id', $embed->id)->delete();

            // 3. Рекурсивно скопировать структуру
            $this->copyBlueprintRecursive(
                blueprint: $embeddedBlueprint,
                hostBlueprint: $hostBlueprint,
                baseParentId: $baseParentId,
                baseParentPath: $baseParentPath,
                rootEmbed: $embed,
                depth: 0
            );
        });
    }

    /**
     * Рекурсивно скопировать структуру blueprint (включая транзитивные embeds).
     *
     * @param Blueprint $blueprint Исходный blueprint (A, C, D, ...)
     * @param Blueprint $hostBlueprint Целевой blueprint (B)
     * @param int|null $baseParentId ID родительского path в B
     * @param string|null $baseParentPath full_path родителя в B
     * @param BlueprintEmbed $rootEmbed Корневой embed B→A (для blueprint_embed_id)
     * @param int $depth Текущая глубина рекурсии
     * @return void
     * @throws MaxDepthExceededException
     */
    private function copyBlueprintRecursive(
        Blueprint $blueprint,
        Blueprint $hostBlueprint,
        ?int $baseParentId,
        ?string $baseParentPath,
        BlueprintEmbed $rootEmbed,
        int $depth
    ): void {
        // Защита от переполнения стека
        if ($depth >= self::MAX_EMBED_DEPTH) {
            throw MaxDepthExceededException::create(self::MAX_EMBED_DEPTH);
        }

        // 1. Получить собственные поля blueprint (без source_blueprint_id)
        $sourcePaths = $blueprint->paths()
            ->whereNull('source_blueprint_id')
            ->orderByRaw('LENGTH(full_path), full_path') // родители раньше детей
            ->get();

        // 2. Карта соответствия: source path id → copy (id, full_path)
        $idMap = [];
        $pathMap = [];

        foreach ($sourcePaths as $source) {
            // Создать копию
            $copy = $source->replicate([
                'blueprint_id',
                'parent_id',
                'full_path',
                'source_blueprint_id',
                'blueprint_embed_id',
                'is_readonly',
            ]);

            // Служебные поля
            $copy->blueprint_id = $hostBlueprint->id;
            $copy->source_blueprint_id = $blueprint->id;
            $copy->blueprint_embed_id = $rootEmbed->id; // ВСЕ копии привязаны к корневому embed
            $copy->is_readonly = true;

            // Вычислить parent_id и full_path
            if ($source->parent_id === null) {
                // Поле верхнего уровня → привязать к baseParent
                $parentId = $baseParentId;
                $parentPath = $baseParentPath;
            } else {
                // Дочернее поле → найти копию родителя
                $parentId = $idMap[$source->parent_id] ?? null;
                $parentPath = $pathMap[$source->parent_id] ?? null;
            }

            $copy->parent_id = $parentId;
            $copy->full_path = $parentPath
                ? $parentPath . '.' . $copy->name
                : $copy->name;

            // Сохранить (UNIQUE constraint требует корректный full_path)
            $copy->save();

            // Запомнить соответствие
            $idMap[$source->id] = $copy->id;
            $pathMap[$source->id] = $copy->full_path;
        }

        // 3. Рекурсивно развернуть внутренние embeds
        $innerEmbeds = $blueprint->embeds()
            ->with(['hostPath', 'embeddedBlueprint'])
            ->get();

        foreach ($innerEmbeds as $innerEmbed) {
            /** @var BlueprintEmbed $innerEmbed */
            $innerHostPath = $innerEmbed->hostPath;

            if ($innerHostPath) {
                // Embed привязан к полю → найти копию этого поля
                $sourceHostId = $innerHostPath->id;

                if (!isset($idMap[$sourceHostId])) {
                    // Теоретически не должно случиться
                    throw new \LogicException(
                        "Не найдена копия host_path для embed {$innerEmbed->id}"
                    );
                }

                $childBaseParentId = $idMap[$sourceHostId];
                $childBaseParentPath = $pathMap[$sourceHostId];
            } else {
                // Embed в корень → базовый путь остаётся тем же
                $childBaseParentId = $baseParentId;
                $childBaseParentPath = $baseParentPath;
            }

            $childBlueprint = $innerEmbed->embeddedBlueprint;

            // Рекурсивный вызов
            $this->copyBlueprintRecursive(
                blueprint: $childBlueprint,
                hostBlueprint: $hostBlueprint,
                baseParentId: $childBaseParentId,
                baseParentPath: $childBaseParentPath,
                rootEmbed: $rootEmbed, // НЕ меняется!
                depth: $depth + 1
            );
        }
    }

    /**
     * Рематериализовать все embeds указанного blueprint.
     *
     * Используется при изменении структуры blueprint.
     *
     * @param Blueprint $blueprint
     * @return void
     */
    public function rematerializeAllEmbeds(Blueprint $blueprint): void
    {
        // Найти все места, где blueprint встроен в другие
        $embeds = BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $blueprint->id)
            ->with(['blueprint', 'embeddedBlueprint', 'hostPath'])
            ->get();

        foreach ($embeds as $embed) {
            $this->materialize($embed);
        }
    }
}

