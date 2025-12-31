<?php

declare(strict_types=1);

namespace App\Services\Path\Constraints;

use App\Models\Path;
use App\Models\PathRefConstraint;

/**
 * Билдер constraints для ref-полей.
 *
 * Реализует работу с constraints для полей с data_type='ref':
 * - Построение constraints для API (Resource и Schema)
 * - Проверка наличия constraints
 * - Синхронизация constraints с базой данных (path_ref_constraints)
 * - Загрузка связей refConstraints
 * - Построение правил валидации для EntryValidationService
 * - Копирование constraints при материализации путей
 *
 * @package App\Services\Path\Constraints
 */
final class RefPathConstraintsBuilder extends AbstractPathConstraintsBuilder
{
    /**
     * Получить тип данных, который поддерживает этот билдер.
     *
     * @return string
     */
    public function getSupportedDataType(): string
    {
        return 'ref';
    }

    /**
     * Построить constraints для ref-полей.
     *
     * Возвращает массив в формате:
     * ['allowed_post_type_ids' => [1, 2, 3]]
     *
     * Используется как для Resource, так и для Schema.
     *
     * @param Path $path Path для построения constraints
     * @return array<string, mixed>
     */
    protected function buildForSupportedDataType(Path $path): array
    {
        $constraints = [];

        // Если связи уже загружены, используем их
        if ($path->relationLoaded('refConstraints') && $path->refConstraints->isNotEmpty()) {
            $constraints['allowed_post_type_ids'] = $path->refConstraints
                ->pluck('allowed_post_type_id')
                ->toArray();
        } elseif ($path->hasRefConstraints()) {
            // Если связи не загружены, загружаем их через метод модели
            $constraints['allowed_post_type_ids'] = $path->getAllowedPostTypeIds();
        }

        return $constraints;
    }

    /**
     * Проверить, есть ли у Path ref constraints.
     *
     * @param Path $path Path для проверки
     * @return bool
     */
    protected function hasConstraintsForSupportedDataType(Path $path): bool
    {
        // Если связи уже загружены, проверяем их
        if ($path->relationLoaded('refConstraints')) {
            return $path->refConstraints->isNotEmpty();
        }

        // Если связи не загружены, используем метод модели
        return $path->hasRefConstraints();
    }

    /**
     * Синхронизировать ref constraints с базой данных.
     *
     * Обновляет constraints в таблице path_ref_constraints:
     * - Удаляет все существующие constraints для Path
     * - Создаёт новые constraints на основе массива allowed_post_type_ids
     * - Использует batch insert для оптимизации
     *
     * @param Path $path Path, для которого синхронизируются constraints
     * @param array<string, mixed> $constraints Массив constraints в формате API (например, ['allowed_post_type_ids' => [1, 2, 3]])
     * @return void
     */
    protected function syncForSupportedDataType(Path $path, array $constraints): void
    {
        // Извлечь allowed_post_type_ids из массива constraints
        $allowedPostTypeIds = $constraints['allowed_post_type_ids'] ?? [];

        // Удалить существующие constraints
        $path->refConstraints()->delete();

        // Создать новые constraints через batch insert
        if (!empty($allowedPostTypeIds) && is_array($allowedPostTypeIds)) {
            $constraintsData = array_map(function ($postTypeId) use ($path) {
                return [
                    'path_id' => $path->id,
                    'allowed_post_type_id' => (int) $postTypeId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $allowedPostTypeIds);

            PathRefConstraint::insert($constraintsData);
        }
    }

    /**
     * Загрузить связи refConstraints для Path.
     *
     * Загружает отношения refConstraints, если они ещё не загружены.
     * Используется для оптимизации запросов (eager loading).
     *
     * @param Path $path Path для загрузки связей
     * @return void
     */
    protected function loadRelationsForSupportedDataType(Path $path): void
    {
        // Загрузить связи, если они ещё не загружены
        if (!$path->relationLoaded('refConstraints')) {
            $path->load('refConstraints');
        }
    }

    /**
     * Получить имя Eloquent связи для eager loading constraints.
     *
     * @return string
     */
    public function getRelationName(): string
    {
        return 'refConstraints';
    }

    /**
     * Построить правило валидации для ref-полей.
     *
     * Создаёт RefPostTypeRule для валидации post_type_id в EntryValidationService.
     * Если у Path нет constraints, возвращает null.
     *
     * @param Path $path Path с загруженными constraints
     * @param \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory Фабрика правил
     * @param string $fieldPath Путь к полю в Entry (например, 'data_json.author')
     * @param string $cardinality Кардинальность поля ('one' или 'many')
     * @return \App\Domain\Blueprint\Validation\Rules\Rule|null
     */
    protected function buildValidationRuleForSupportedDataType(
        Path $path,
        \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory,
        string $fieldPath,
        string $cardinality
    ): ?\App\Domain\Blueprint\Validation\Rules\Rule {
        // Проверить наличие constraints
        if (!$this->hasConstraintsForSupportedDataType($path)) {
            return null;
        }

        // Извлечь allowed_post_type_ids
        $allowedPostTypeIds = $this->buildForSupportedDataType($path)['allowed_post_type_ids'] ?? [];

        if (empty($allowedPostTypeIds)) {
            return null;
        }

        // Создать правило валидации через фабрику
        return $ruleFactory->createRefPostTypeRule($allowedPostTypeIds, $path->full_path);
    }

    /**
     * Скопировать ref constraints из source Path в target Path.
     *
     * Выполняет batch insert constraints для всех ref-полей, которые имеют constraints.
     * Использует batch insert для оптимизации производительности.
     *
     * @param Path $sourcePath Исходный Path с загруженными refConstraints
     * @param int $targetPathId ID целевого Path
     * @param int $batchInsertSize Размер batch для вставки
     * @return void
     */
    protected function copyConstraintsForSupportedDataType(
        Path $sourcePath,
        int $targetPathId,
        int $batchInsertSize
    ): void {
        // Убедиться, что связи загружены (должно быть проверено в родительском методе)
        if (!$sourcePath->relationLoaded('refConstraints') || $sourcePath->refConstraints->isEmpty()) {
            return;
        }

        $constraintsToInsert = [];
        $now = now();

        // Собрать constraints для batch insert
        foreach ($sourcePath->refConstraints as $constraint) {
            $constraintsToInsert[] = [
                'path_id' => $targetPathId,
                'allowed_post_type_id' => $constraint->allowed_post_type_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Batch insert всех constraints
        if (!empty($constraintsToInsert)) {
            // Разбить на chunks для защиты от max_allowed_packet в MySQL
            foreach (array_chunk($constraintsToInsert, $batchInsertSize) as $chunk) {
                PathRefConstraint::insert($chunk);
            }
        }
    }
}

