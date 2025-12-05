<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Events\Blueprint\BlueprintStructureChanged;
use App\Exceptions\Blueprint\BlueprintEmbeddedException;
use App\Exceptions\Blueprint\BlueprintUsedInPostTypeException;
use App\Exceptions\Blueprint\CannotDeleteCopiedPathException;
use App\Exceptions\Blueprint\CannotEditCopiedPathException;
use App\Exceptions\Blueprint\CyclicDependencyException;
use App\Exceptions\Blueprint\DuplicateEmbedException;
use App\Exceptions\Blueprint\DuplicatePathException;
use App\Exceptions\Blueprint\InvalidHostPathException;
use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для работы со структурой Blueprint.
 *
 * Координирует создание/изменение/удаление Blueprint, Path, BlueprintEmbed.
 * Использует валидаторы, материализацию и каскадные события.
 *
 * Все исключения реализуют ErrorConvertible и автоматически обрабатываются
 * через систему управления ошибками (ErrorKernel).
 */
class BlueprintStructureService
{
    /**
     * @param CyclicDependencyValidator $cyclicValidator
     * @param PathConflictValidator $conflictValidator
     * @param MaterializationService $materializationService
     */
    public function __construct(
        private readonly CyclicDependencyValidator $cyclicValidator,
        private readonly PathConflictValidator $conflictValidator,
        private readonly MaterializationService $materializationService
    ) {}

    // ============================================
    // CRUD: Blueprint
    // ============================================

    /**
     * Создать новый Blueprint.
     *
     * @param array{name: string, code: string, description?: string} $data
     * @return Blueprint
     */
    public function createBlueprint(array $data): Blueprint
    {
        return Blueprint::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Обновить Blueprint.
     *
     * @param Blueprint $blueprint
     * @param array{name?: string, code?: string, description?: string} $data
     * @return Blueprint
     */
    public function updateBlueprint(Blueprint $blueprint, array $data): Blueprint
    {
        $blueprint->update($data);
        return $blueprint->fresh();
    }

    /**
     * Удалить Blueprint.
     *
     * Проверяет, что blueprint не используется в PostType.
     *
     * @param Blueprint $blueprint
     * @return void
     * @throws BlueprintUsedInPostTypeException
     * @throws BlueprintEmbeddedException
     */
    public function deleteBlueprint(Blueprint $blueprint): void
    {
        // Проверить, не используется ли blueprint в PostType
        $postTypesCount = \App\Models\PostType::query()
            ->where('blueprint_id', $blueprint->id)
            ->count();

        if ($postTypesCount > 0) {
            throw BlueprintUsedInPostTypeException::create($blueprint->code, $postTypesCount);
        }

        // Проверить, не встроен ли в другие blueprint
        $embedsCount = BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $blueprint->id)
            ->count();

        if ($embedsCount > 0) {
            throw BlueprintEmbeddedException::create($blueprint->code, $embedsCount);
        }

        $blueprint->delete();
    }

    // ============================================
    // CRUD: Path
    // ============================================

    /**
     * Создать собственное поле в Blueprint.
     *
     * @param Blueprint $blueprint
     * @param array{
     *     name: string,
     *     parent_id?: int|null,
     *     data_type: string,
     *     cardinality?: string,
     *     is_indexed?: bool,
     *     sort_order?: int,
     *     validation_rules?: array
     * } $data
     * @return Path
     * @throws DuplicatePathException Если путь с таким full_path уже существует
     * @throws InvalidHostPathException Если parent_id не принадлежит blueprint
     */
    public function createPath(Blueprint $blueprint, array $data): Path
    {
        return DB::transaction(function () use ($blueprint, $data) {
            // 1. Проверить и загрузить parent_path
            $parentPath = null;
            if (isset($data['parent_id'])) {
                $parentPath = Path::find($data['parent_id']);
                
                if ($parentPath === null) {
                    throw InvalidHostPathException::notOwnedByBlueprint(
                        'parent_id=' . $data['parent_id'],
                        $blueprint->code
                    );
                }

                // Проверить, что parent_path принадлежит тому же blueprint
                if ($parentPath->blueprint_id !== $blueprint->id) {
                    throw InvalidHostPathException::notOwnedByBlueprint(
                        $parentPath->full_path,
                        $blueprint->code
                    );
                }
            }

            // 2. Вычислить full_path
            $fullPath = $parentPath
                ? $parentPath->full_path . '.' . $data['name']
                : $data['name'];

            // 3. Проверить уникальность full_path в blueprint
            $exists = Path::query()
                ->where('blueprint_id', $blueprint->id)
                ->where('full_path', $fullPath)
                ->exists();

            if ($exists) {
                throw DuplicatePathException::create($fullPath, $blueprint->code);
            }

            // 4. Создать path
            try {
                $path = new Path();
                $path->forceFill([
                    'blueprint_id' => $blueprint->id,
                    'parent_id' => $data['parent_id'] ?? null,
                    'name' => $data['name'],
                    'full_path' => $fullPath,
                    'data_type' => $data['data_type'],
                    'cardinality' => $data['cardinality'] ?? 'one',
                    'is_indexed' => $data['is_indexed'] ?? false,
                    'sort_order' => $data['sort_order'] ?? 0,
                    'validation_rules' => $data['validation_rules'] ?? null,
                ]);
                $path->save();
            } catch (QueryException $e) {
                // Обработка ошибки уникальности из БД (на случай race condition)
                if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'uq_paths_full_path_per_blueprint')) {
                    throw DuplicatePathException::create($fullPath, $blueprint->code);
                }
                throw $e;
            }

            // 5. Событие изменения структуры
            event(new BlueprintStructureChanged($blueprint));

            return $path;
        });
    }

    /**
     * Обновить собственное поле Blueprint.
     *
     * @param Path $path
     * @param array<string, mixed> $data
     * @return Path
     * @throws CannotEditCopiedPathException
     */
    public function updatePath(Path $path, array $data): Path
    {
        // Валидация: нельзя редактировать скопированные поля
        if ($path->isCopied()) {
            $path->load('sourceBlueprint');
            $sourceBlueprintCode = $path->sourceBlueprint?->code ?? 'unknown';
            
            throw CannotEditCopiedPathException::create($path->full_path, $sourceBlueprintCode);
        }

        return DB::transaction(function () use ($path, $data) {
            // Если меняется name или parent_id — пересчитать full_path
            $needsFullPathUpdate = isset($data['name']) || isset($data['parent_id']);

            $path->update($data);

            if ($needsFullPathUpdate) {
                $this->recalculateFullPath($path);
            }

            // Событие изменения структуры
            event(new BlueprintStructureChanged($path->blueprint));

            return $path->fresh();
        });
    }

    /**
     * Удалить собственное поле Blueprint.
     *
     * @param Path $path
     * @return void
     * @throws CannotDeleteCopiedPathException
     */
    public function deletePath(Path $path): void
    {
        // Валидация: нельзя удалять скопированные поля
        if ($path->isCopied()) {
            throw CannotDeleteCopiedPathException::create($path->full_path, $path->blueprint->code);
        }

        DB::transaction(function () use ($path) {
            $blueprint = $path->blueprint;

            // Удалить (дочерние удалятся CASCADE)
            $path->delete();

            // Событие изменения структуры
            event(new BlueprintStructureChanged($blueprint));
        });
    }

    /**
     * Пересчитать full_path для поля и всех дочерних.
     *
     * @param Path $path
     * @return void
     */
    private function recalculateFullPath(Path $path): void
    {
        $path->refresh();

        $newFullPath = $path->parent
            ? $path->parent->full_path . '.' . $path->name
            : $path->name;

        if ($path->full_path !== $newFullPath) {
            $path->forceFill(['full_path' => $newFullPath]);
            $path->saveQuietly(); // без триггера событий

            // Рекурсивно обновить дочерние
            foreach ($path->children as $child) {
                $this->recalculateFullPath($child);
            }
        }
    }

    // ============================================
    // CRUD: BlueprintEmbed
    // ============================================

    /**
     * Создать встраивание с полной валидацией и материализацией.
     *
     * @param Blueprint $host Кто встраивает
     * @param Blueprint $embedded Кого встраивают
     * @param Path|null $hostPath Поле-контейнер (NULL = корень)
     * @return BlueprintEmbed
     * @throws CyclicDependencyException
     * @throws PathConflictException
     * @throws DuplicateEmbedException Если встраивание уже существует в указанном месте
     * @throws InvalidHostPathException Если host_path невалиден
     */
    public function createEmbed(
        Blueprint $host,
        Blueprint $embedded,
        ?Path $hostPath = null
    ): BlueprintEmbed {
        return DB::transaction(function () use ($host, $embedded, $hostPath) {
            // 1. Валидация циклов
            $this->cyclicValidator->ensureNoCyclicDependency($host, $embedded);

            // 2. Валидация host_path
            $this->validateHostPath($host, $hostPath);

            // 3. Проверка уникальности (blueprint_id, embedded_blueprint_id, host_path_id)
            $exists = BlueprintEmbed::query()
                ->where('blueprint_id', $host->id)
                ->where('embedded_blueprint_id', $embedded->id)
                ->where('host_path_id', $hostPath?->id)
                ->exists();

            if ($exists) {
                throw DuplicateEmbedException::create(
                    $host->code,
                    $embedded->code,
                    $hostPath?->full_path
                );
            }

            // 4. Создание embed
            $embed = BlueprintEmbed::create([
                'blueprint_id' => $host->id,
                'embedded_blueprint_id' => $embedded->id,
                'host_path_id' => $hostPath?->id,
            ]);

            // 5. Материализация (с PRE-CHECK конфликтов внутри)
            $this->materializationService->materialize($embed);

            // 6. Событие для реиндексации
            event(new BlueprintStructureChanged($host));

            Log::info("Создано встраивание: '{$embedded->code}' → '{$host->code}'", [
                'embed_id' => $embed->id,
                'host_path' => $hostPath?->full_path,
            ]);

            return $embed;
        });
    }

    /**
     * Удалить встраивание.
     *
     * Скопированные поля удалятся автоматически (CASCADE).
     *
     * @param BlueprintEmbed $embed
     * @return void
     */
    public function deleteEmbed(BlueprintEmbed $embed): void
    {
        DB::transaction(function () use ($embed) {
            $host = $embed->blueprint;
            $embedded = $embed->embeddedBlueprint;

            // Удалить embed (копии полей удалятся CASCADE)
            $embed->delete();

            // Событие для реиндексации
            event(new BlueprintStructureChanged($host));

            Log::info("Удалено встраивание: '{$embedded->code}' из '{$host->code}'", [
                'embed_id' => $embed->id,
            ]);
        });
    }

    /**
     * Валидировать host_path.
     *
     * @param Blueprint $blueprint
     * @param Path|null $hostPath
     * @return void
     * @throws InvalidHostPathException
     */
    private function validateHostPath(Blueprint $blueprint, ?Path $hostPath): void
    {
        if ($hostPath === null) {
            return; // Встраивание в корень
        }

        // Проверить принадлежность к blueprint
        if ($hostPath->blueprint_id !== $blueprint->id) {
            throw InvalidHostPathException::notOwnedByBlueprint($hostPath->full_path, $blueprint->code);
        }

        // Проверить, что host_path — не скопированное поле
        if ($hostPath->isCopied()) {
            throw InvalidHostPathException::isCopied($hostPath->full_path);
        }

        // Опционально: проверить тип (должна быть группа)
        if ($hostPath->data_type !== 'json') {
            throw InvalidHostPathException::notAGroup($hostPath->full_path);
        }
    }

    // ============================================
    // Вспомогательные методы
    // ============================================

    /**
     * Получить список blueprint'ов, в которые можно встроить указанный.
     *
     * Исключает сам blueprint и те, которые создадут цикл.
     *
     * @param Blueprint $blueprint
     * @return \Illuminate\Support\Collection<int, Blueprint>
     */
    public function getEmbeddableBlueprintsFor(Blueprint $blueprint): \Illuminate\Support\Collection
    {
        $allBlueprints = Blueprint::all();

        // Получить ID blueprint'ов, которые уже встроили этот blueprint
        $alreadyEmbeddedIn = BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $blueprint->id)
            ->pluck('blueprint_id')
            ->all();

        return $allBlueprints->filter(function ($candidate) use ($blueprint, $alreadyEmbeddedIn) {
            // Нельзя встроить в самого себя
            if ($candidate->id === $blueprint->id) {
                return false;
            }

            // Нельзя встроить в blueprint, который уже встроил этот blueprint
            if (in_array($candidate->id, $alreadyEmbeddedIn, true)) {
                return false;
            }

            // Проверить, не создаст ли цикл
            // $candidate (host) встраивает $blueprint (embedded)
            // Параметры: canEmbed(hostId, embeddedId)
            return $this->cyclicValidator->canEmbed($candidate->id, $blueprint->id);
        });
    }

    /**
     * Получить граф зависимостей blueprint.
     *
     * @param Blueprint $blueprint
     * @return array{
     *     depends_on: array<int>,
     *     depended_by: array<int>
     * }
     */
    public function getDependencyGraph(Blueprint $blueprint): array
    {
        $graphService = app(DependencyGraphService::class);

        return [
            'depends_on' => $graphService->getAllTransitiveDependencies($blueprint->id)->all(),
            'depended_by' => $graphService->getAllDependentBlueprintIds($blueprint->id)->all(),
        ];
    }

    /**
     * Проверить, можно ли удалить Blueprint.
     *
     * @param Blueprint $blueprint
     * @return array{can_delete: bool, reasons: array<string>}
     */
    public function canDeleteBlueprint(Blueprint $blueprint): array
    {
        $reasons = [];

        // Проверить использование в PostType
        $postTypesCount = \App\Models\PostType::query()
            ->where('blueprint_id', $blueprint->id)
            ->count();

        if ($postTypesCount > 0) {
            $reasons[] = "Используется в {$postTypesCount} PostType";
        }

        // Проверить встраивания
        $embedsCount = BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $blueprint->id)
            ->count();

        if ($embedsCount > 0) {
            $reasons[] = "Встроен в {$embedsCount} других blueprint";
        }

        return [
            'can_delete' => empty($reasons),
            'reasons' => $reasons,
        ];
    }
}

