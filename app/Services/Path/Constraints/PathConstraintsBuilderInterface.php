<?php

declare(strict_types=1);

namespace App\Services\Path\Constraints;

use App\Models\Path;

/**
 * Интерфейс для билдеров constraints Path.
 *
 * Каждый билдер отвечает за работу с constraints для определённого типа данных.
 * Билдер объединяет логику:
 * - Сериализации constraints для API (Resource и Schema)
 * - Проверки наличия constraints
 * - Синхронизации constraints с базой данных
 * - Загрузки необходимых связей (eager loading)
 * - Построения правил валидации для EntryValidationService
 * - Копирования constraints при материализации путей
 *
 * Примеры реализации:
 * - RefPathConstraintsBuilder - для data_type='ref'
 * - MediaPathConstraintsBuilder - для data_type='media'
 *
 * @package App\Services\Path\Constraints
 */
interface PathConstraintsBuilderInterface
{
    /**
     * Получить тип данных, который поддерживает этот билдер.
     *
     * Билдер обрабатывает constraints только для указанного типа данных.
     * Например, RefPathConstraintsBuilder возвращает 'ref',
     * а MediaPathConstraintsBuilder возвращает 'media'.
     *
     * @return string Тип данных (например, 'ref', 'media')
     */
    public function getSupportedDataType(): string;

    /**
     * Построить constraints для PathResource (API ответ).
     *
     * Преобразует constraints из модели Path в массив для ответа API.
     * Используется в PathResource::getConstraintsArray().
     *
     * @param Path $path Path для построения constraints
     * @return array<string, mixed> Массив constraints в формате для API (например, ['allowed_post_type_ids' => [1, 2, 3]])
     */
    public function buildForResource(Path $path): array;

    /**
     * Построить constraints для JSON схемы Blueprint.
     *
     * Преобразует constraints из модели Path в массив для схемы blueprint.
     * Используется в BlueprintController::buildConstraintsForSchema().
     * Формат должен совпадать с buildForResource().
     *
     * @param Path $path Path для построения constraints
     * @return array<string, mixed> Массив constraints в формате для схемы (например, ['allowed_post_type_ids' => [1, 2, 3]])
     */
    public function buildForSchema(Path $path): array;

    /**
     * Проверить, есть ли у Path constraints данного типа.
     *
     * Используется для определения, нужно ли включать constraints в ответ API.
     *
     * @param Path $path Path для проверки
     * @return bool true, если constraints существуют
     */
    public function hasConstraints(Path $path): bool;

    /**
     * Синхронизировать constraints с базой данных.
     *
     * Обновляет constraints в БД на основе переданного массива:
     * - Удаляет старые constraints
     * - Создаёт новые constraints из массива
     * - Использует batch insert для оптимизации при множественных constraints
     *
     * Массив constraints должен быть в том же формате, что возвращает buildForResource().
     * Если массив пустой или не содержит нужных ключей, constraints могут быть удалены.
     *
     * @param Path $path Path, для которого синхронизируются constraints
     * @param array<string, mixed> $constraints Массив constraints в формате API (например, ['allowed_post_type_ids' => [1, 2, 3]])
     * @return void
     */
    public function sync(Path $path, array $constraints): void;

    /**
     * Загрузить необходимые связи для Path (eager loading).
     *
     * Вызывается перед использованием билдера для оптимизации запросов.
     * Если связи уже загружены, метод может ничего не делать.
     *
     * Примеры:
     * - RefPathConstraintsBuilder загружает 'refConstraints'
     * - MediaPathConstraintsBuilder может загружать будущие связи для media
     *
     * @param Path $path Path для загрузки связей
     * @return void
     */
    public function loadRelations(Path $path): void;

    /**
     * Построить доменное правило валидации для EntryValidationService.
     *
     * Создаёт Rule объект для валидации значений полей Entry на основе constraints Path.
     * Используется в EntryValidationService для автоматической валидации constraints.
     *
     * Если у Path нет constraints или constraints не требуют валидации, возвращает null.
     *
     * @param Path $path Path с загруженными constraints
     * @param \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory Фабрика правил
     * @param string $fieldPath Путь к полю в Entry (например, 'data_json.author')
     * @param string $cardinality Кардинальность поля ('one' или 'many')
     * @return \App\Domain\Blueprint\Validation\Rules\Rule|null Правило валидации или null, если constraints нет
     */
    public function buildValidationRule(
        Path $path,
        \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory,
        string $fieldPath,
        string $cardinality
    ): ?\App\Domain\Blueprint\Validation\Rules\Rule;

    /**
     * Получить имя Eloquent связи для eager loading constraints.
     *
     * Используется для универсального загрузки constraints через with().
     * Например, для ref это 'refConstraints', для media может быть 'mediaConstraints'.
     *
     * Если связи ещё не существует (для будущих типов), возвращает пустую строку.
     *
     * @return string Имя связи (например, 'refConstraints') или пустая строка, если связи нет
     */
    public function getRelationName(): string;

    /**
     * Скопировать constraints из source Path в target Path.
     *
     * Используется в PathMaterializer для копирования constraints при материализации путей.
     * Выполняет batch insert для оптимизации производительности.
     *
     * Если у source Path нет constraints или они не загружены, метод ничего не делает.
     *
     * @param Path $sourcePath Исходный Path с загруженными constraints
     * @param int $targetPathId ID целевого Path
     * @param int $batchInsertSize Размер batch для вставки
     * @return void
     */
    public function copyConstraints(Path $sourcePath, int $targetPathId, int $batchInsertSize): void;
}

