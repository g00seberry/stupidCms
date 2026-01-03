<?php

declare(strict_types=1);

namespace App\Services\Path\Constraints;

use App\Models\Path;
use App\Models\PathMediaConstraint;

/**
 * Билдер constraints для media-полей.
 *
 * Реализует работу с constraints для полей с data_type='media':
 * - Построение constraints для API (Resource и Schema)
 * - Проверка наличия constraints
 * - Синхронизация constraints с базой данных (path_media_constraints)
 * - Загрузка связей для media constraints
 * - Построение правил валидации для EntryValidationService
 * - Копирование constraints при материализации путей
 *
 * @package App\Services\Path\Constraints
 */
final class MediaPathConstraintsBuilder extends AbstractPathConstraintsBuilder
{
    /**
     * Получить тип данных, который поддерживает этот билдер.
     *
     * @return string
     */
    public function getSupportedDataType(): string
    {
        return 'media';
    }

    /**
     * Построить constraints для media-полей.
     *
     * Возвращает массив в формате:
     * ['allowed_mimes' => ['image/jpeg', 'image/png', ...]]
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
        if ($path->relationLoaded('mediaConstraints') && $path->mediaConstraints->isNotEmpty()) {
            $constraints['allowed_mimes'] = $path->mediaConstraints
                ->pluck('allowed_mime')
                ->toArray();
        } elseif ($path->hasMediaConstraints()) {
            // Если связи не загружены, загружаем их через метод модели
            $constraints['allowed_mimes'] = $path->getAllowedMimeTypes();
        }

        return $constraints;
    }

    /**
     * Проверить, есть ли у Path media constraints.
     *
     * @param Path $path Path для проверки
     * @return bool
     */
    protected function hasConstraintsForSupportedDataType(Path $path): bool
    {
        // Если связи уже загружены, проверяем их
        if ($path->relationLoaded('mediaConstraints')) {
            return $path->mediaConstraints->isNotEmpty();
        }

        // Если связи не загружены, используем метод модели
        return $path->hasMediaConstraints();
    }

    /**
     * Синхронизировать media constraints с базой данных.
     *
     * Обновляет constraints в таблице path_media_constraints:
     * - Удаляет все существующие constraints для Path
     * - Создаёт новые constraints на основе массива allowed_mimes
     * - Использует batch insert для оптимизации
     *
     * @param Path $path Path, для которого синхронизируются constraints
     * @param array<string, mixed> $constraints Массив constraints в формате API (например, ['allowed_mimes' => [...]])
     * @return void
     */
    protected function syncForSupportedDataType(Path $path, array $constraints): void
    {
        // Извлечь allowed_mimes из массива constraints
        $allowedMimes = $constraints['allowed_mimes'] ?? [];

        // Удалить существующие constraints
        $path->mediaConstraints()->delete();

        // Создать новые constraints через batch insert
        if (!empty($allowedMimes) && is_array($allowedMimes)) {
            $constraintsData = array_map(function ($mime) use ($path) {
                return [
                    'path_id' => $path->id,
                    'allowed_mime' => (string) $mime,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $allowedMimes);

            PathMediaConstraint::insert($constraintsData);
        }
    }

    /**
     * Загрузить связи mediaConstraints для Path.
     *
     * Загружает отношения mediaConstraints, если они ещё не загружены.
     * Используется для оптимизации запросов (eager loading).
     *
     * @param Path $path Path для загрузки связей
     * @return void
     */
    protected function loadRelationsForSupportedDataType(Path $path): void
    {
        // Загрузить связи, если они ещё не загружены
        if (!$path->relationLoaded('mediaConstraints')) {
            $path->load('mediaConstraints');
        }
    }

    /**
     * Получить имя Eloquent связи для eager loading constraints.
     *
     * @return string
     */
    public function getRelationName(): string
    {
        return 'mediaConstraints';
    }

    /**
     * Построить правило валидации для media-полей.
     *
     * Создаёт MediaMimeRule для валидации MIME типов в EntryValidationService.
     * Если у Path нет constraints, возвращает null.
     *
     * @param Path $path Path с загруженными constraints
     * @param \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory Фабрика правил
     * @param string $fieldPath Путь к полю в Entry (например, 'data_json.avatar')
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

        // Извлечь allowed_mimes
        $allowedMimes = $this->buildForSupportedDataType($path)['allowed_mimes'] ?? [];

        if (empty($allowedMimes)) {
            return null;
        }

        // Создать правило валидации через фабрику
        return $ruleFactory->createMediaMimeRule($allowedMimes, $path->full_path);
    }

    /**
     * Скопировать media constraints из source Path в target Path.
     *
     * Выполняет batch insert constraints для всех media-полей, которые имеют constraints.
     * Использует batch insert для оптимизации производительности.
     *
     * @param Path $sourcePath Исходный Path с загруженными mediaConstraints
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
        if (!$sourcePath->relationLoaded('mediaConstraints') || $sourcePath->mediaConstraints->isEmpty()) {
            return;
        }

        $constraintsToInsert = [];
        $now = now();

        // Собрать constraints для batch insert
        foreach ($sourcePath->mediaConstraints as $constraint) {
            $constraintsToInsert[] = [
                'path_id' => $targetPathId,
                'allowed_mime' => $constraint->allowed_mime,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Batch insert всех constraints
        if (!empty($constraintsToInsert)) {
            // Разбить на chunks для защиты от max_allowed_packet в MySQL
            foreach (array_chunk($constraintsToInsert, $batchInsertSize) as $chunk) {
                PathMediaConstraint::insert($chunk);
            }
        }
    }
}

