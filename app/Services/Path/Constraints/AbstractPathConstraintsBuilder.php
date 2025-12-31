<?php

declare(strict_types=1);

namespace App\Services\Path\Constraints;

use App\Models\Path;

/**
 * Абстрактный базовый класс для билдеров constraints Path.
 *
 * Предоставляет общую функциональность и шаблонные методы,
 * которые могут быть переопределены в конкретных реализациях.
 *
 * @package App\Services\Path\Constraints
 */
abstract class AbstractPathConstraintsBuilder implements PathConstraintsBuilderInterface
{
    /**
     * Построить constraints для PathResource (API ответ).
     *
     * Базовая реализация проверяет, соответствует ли data_type поддерживаемому типу.
     * Если да - делегирует построение методу buildForSupportedDataType().
     * Если нет - возвращает пустой массив.
     *
     * @param Path $path Path для построения constraints
     * @return array<string, mixed>
     */
    public function buildForResource(Path $path): array
    {
        if (!$this->supportsDataType($path->data_type)) {
            return [];
        }

        return $this->buildForSupportedDataType($path);
    }

    /**
     * Построить constraints для JSON схемы Blueprint.
     *
     * Базовая реализация проверяет, соответствует ли data_type поддерживаемому типу.
     * Если да - делегирует построение методу buildForSupportedDataType().
     * Если нет - возвращает пустой массив.
     *
     * Формат совпадает с buildForResource().
     *
     * @param Path $path Path для построения constraints
     * @return array<string, mixed>
     */
    public function buildForSchema(Path $path): array
    {
        if (!$this->supportsDataType($path->data_type)) {
            return [];
        }

        return $this->buildForSupportedDataType($path);
    }

    /**
     * Проверить, есть ли у Path constraints данного типа.
     *
     * Базовая реализация проверяет, соответствует ли data_type поддерживаемому типу.
     * Если да - делегирует проверку методу hasConstraintsForSupportedDataType().
     * Если нет - возвращает false.
     *
     * @param Path $path Path для проверки
     * @return bool
     */
    public function hasConstraints(Path $path): bool
    {
        if (!$this->supportsDataType($path->data_type)) {
            return false;
        }

        return $this->hasConstraintsForSupportedDataType($path);
    }

    /**
     * Синхронизировать constraints с базой данных.
     *
     * Базовая реализация проверяет, соответствует ли data_type поддерживаемому типу.
     * Если да - делегирует синхронизацию методу syncForSupportedDataType().
     * Если нет - ничего не делает.
     *
     * @param Path $path Path, для которого синхронизируются constraints
     * @param array<string, mixed> $constraints Массив constraints в формате API
     * @return void
     */
    public function sync(Path $path, array $constraints): void
    {
        if (!$this->supportsDataType($path->data_type)) {
            return;
        }

        $this->syncForSupportedDataType($path, $constraints);
    }

    /**
     * Загрузить необходимые связи для Path (eager loading).
     *
     * Базовая реализация проверяет, соответствует ли data_type поддерживаемому типу.
     * Если да - делегирует загрузку методу loadRelationsForSupportedDataType().
     * Если нет - ничего не делает.
     *
     * @param Path $path Path для загрузки связей
     * @return void
     */
    public function loadRelations(Path $path): void
    {
        if (!$this->supportsDataType($path->data_type)) {
            return;
        }

        $this->loadRelationsForSupportedDataType($path);
    }

    /**
     * Построить доменное правило валидации для EntryValidationService.
     *
     * Базовая реализация проверяет, соответствует ли data_type поддерживаемому типу.
     * Если да - делегирует построение методу buildValidationRuleForSupportedDataType().
     * Если нет - возвращает null.
     *
     * @param Path $path Path с загруженными constraints
     * @param \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory Фабрика правил
     * @param string $fieldPath Путь к полю в Entry (например, 'data_json.author')
     * @param string $cardinality Кардинальность поля ('one' или 'many')
     * @return \App\Domain\Blueprint\Validation\Rules\Rule|null Правило валидации или null
     */
    public function buildValidationRule(
        Path $path,
        \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory,
        string $fieldPath,
        string $cardinality
    ): ?\App\Domain\Blueprint\Validation\Rules\Rule {
        if (!$this->supportsDataType($path->data_type)) {
            return null;
        }

        return $this->buildValidationRuleForSupportedDataType($path, $ruleFactory, $fieldPath, $cardinality);
    }

    /**
     * Скопировать constraints из source Path в target Path.
     *
     * Базовая реализация проверяет:
     * - Соответствует ли data_type поддерживаемому типу
     * - Загружены ли связи (через getRelationName())
     * - Есть ли данные в загруженных связях
     *
     * Если все условия выполнены - делегирует копирование методу copyConstraintsForSupportedDataType().
     * Если нет - ничего не делает.
     *
     * @param Path $sourcePath Исходный Path с загруженными constraints
     * @param int $targetPathId ID целевого Path
     * @param int $batchInsertSize Размер batch для вставки
     * @return void
     */
    public function copyConstraints(Path $sourcePath, int $targetPathId, int $batchInsertSize): void
    {
        if (!$this->supportsDataType($sourcePath->data_type)) {
            return;
        }

        $relationName = $this->getRelationName();
        
        // Если связи нет (пустая строка), ничего не делаем
        if ($relationName === '') {
            return;
        }

        // Проверяем, загружена ли связь
        if (!$sourcePath->relationLoaded($relationName)) {
            return;
        }

        // Проверяем, есть ли данные в связи
        $relation = $sourcePath->getRelation($relationName);
        if ($relation === null || ($relation instanceof \Illuminate\Database\Eloquent\Collection && $relation->isEmpty())) {
            return;
        }

        $this->copyConstraintsForSupportedDataType($sourcePath, $targetPathId, $batchInsertSize);
    }

    /**
     * Проверить, поддерживает ли билдер указанный тип данных.
     *
     * @param string $dataType Тип данных для проверки
     * @return bool true, если тип данных поддерживается
     */
    protected function supportsDataType(string $dataType): bool
    {
        return $dataType === $this->getSupportedDataType();
    }

    /**
     * Построить constraints для поддерживаемого типа данных.
     *
     * Метод должен быть реализован в подклассах для построения конкретных constraints.
     * Этот метод вызывается только если data_type соответствует getSupportedDataType().
     * Формат результата используется как для Resource, так и для Schema.
     *
     * @param Path $path Path для построения constraints
     * @return array<string, mixed> Массив constraints (например, ['allowed_post_type_ids' => [1, 2, 3]])
     */
    abstract protected function buildForSupportedDataType(Path $path): array;

    /**
     * Проверить, есть ли у Path constraints для поддерживаемого типа данных.
     *
     * Метод должен быть реализован в подклассах для проверки наличия constraints.
     * Этот метод вызывается только если data_type соответствует getSupportedDataType().
     *
     * @param Path $path Path для проверки
     * @return bool true, если constraints существуют
     */
    abstract protected function hasConstraintsForSupportedDataType(Path $path): bool;

    /**
     * Синхронизировать constraints для поддерживаемого типа данных.
     *
     * Метод должен быть реализован в подклассах для синхронизации constraints.
     * Этот метод вызывается только если data_type соответствует getSupportedDataType().
     *
     * @param Path $path Path, для которого синхронизируются constraints
     * @param array<string, mixed> $constraints Массив constraints в формате API
     * @return void
     */
    abstract protected function syncForSupportedDataType(Path $path, array $constraints): void;

    /**
     * Загрузить необходимые связи для поддерживаемого типа данных.
     *
     * Метод должен быть реализован в подклассах для загрузки связей.
     * Этот метод вызывается только если data_type соответствует getSupportedDataType().
     *
     * @param Path $path Path для загрузки связей
     * @return void
     */
    abstract protected function loadRelationsForSupportedDataType(Path $path): void;

    /**
     * Построить правило валидации для поддерживаемого типа данных.
     *
     * Метод должен быть реализован в подклассах для построения конкретных правил валидации.
     * Этот метод вызывается только если data_type соответствует getSupportedDataType().
     *
     * Если у Path нет constraints или constraints не требуют валидации, возвращает null.
     *
     * @param Path $path Path с загруженными constraints
     * @param \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory Фабрика правил
     * @param string $fieldPath Путь к полю в Entry (например, 'data_json.author')
     * @param string $cardinality Кардинальность поля ('one' или 'many')
     * @return \App\Domain\Blueprint\Validation\Rules\Rule|null Правило валидации или null
     */
    abstract protected function buildValidationRuleForSupportedDataType(
        Path $path,
        \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory,
        string $fieldPath,
        string $cardinality
    ): ?\App\Domain\Blueprint\Validation\Rules\Rule;

    /**
     * Скопировать constraints для поддерживаемого типа данных.
     *
     * Метод должен быть реализован в подклассах для копирования constraints.
     * Этот метод вызывается только если:
     * - data_type соответствует getSupportedDataType()
     * - связи загружены и содержат данные
     *
     * Выполняет batch insert для оптимизации производительности.
     *
     * @param Path $sourcePath Исходный Path с загруженными constraints
     * @param int $targetPathId ID целевого Path
     * @param int $batchInsertSize Размер batch для вставки
     * @return void
     */
    abstract protected function copyConstraintsForSupportedDataType(
        Path $sourcePath,
        int $targetPathId,
        int $batchInsertSize
    ): void;
}

