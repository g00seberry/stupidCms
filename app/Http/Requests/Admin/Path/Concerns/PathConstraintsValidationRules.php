<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path\Concerns;

use App\Http\Requests\Admin\Path\Constraints\ConstraintsValidationBuilderRegistry;
use App\Models\Path;

/**
 * Трейт с правилами валидации для constraints Path.
 *
 * Содержит методы для сборки правил валидации constraints для StorePathRequest и UpdatePathRequest.
 * Использует регистр билдеров для динамического построения правил на основе типа данных.
 *
 * @package App\Http\Requests\Admin\Path\Concerns
 */
trait PathConstraintsValidationRules
{
    /**
     * Получить регистр билдеров валидации constraints.
     *
     * @return ConstraintsValidationBuilderRegistry
     */
    protected function getConstraintsRegistry(): ConstraintsValidationBuilderRegistry
    {
        return app(ConstraintsValidationBuilderRegistry::class);
    }

    /**
     * Получить правила валидации для constraints при создании Path (StorePathRequest).
     *
     * Правила строятся динамически на основе data_type поля:
     * - Получает data_type из запроса
     * - Находит соответствующий билдер через регистр
     * - Если билдер найден - использует его правила
     * - Если билдер не найден - запрещает constraints для данного типа данных
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function getConstraintsRulesForStore(): array
    {
        $dataType = $this->input('data_type');
        
        if (!is_string($dataType)) {
            // Если data_type не передан, запрещаем constraints
            return ['constraints' => ['prohibited']];
        }

        $registry = $this->getConstraintsRegistry();
        $builder = $registry->getBuilder($dataType);

        if ($builder === null) {
            // Если билдер не найден для данного типа данных, запрещаем constraints
            return ['constraints' => ['prohibited']];
        }

        return $builder->buildRulesForStore($dataType);
    }

    /**
     * Получить правила валидации для constraints при обновлении Path (UpdatePathRequest).
     *
     * Правила строятся динамически на основе текущего data_type из модели Path:
     * - Получает текущий data_type из модели Path (data_type нельзя изменять)
     * - Находит соответствующий билдер через регистр
     * - Если билдер найден - использует его правила
     * - Если билдер не найден - запрещает constraints для данного типа данных
     *
     * @param Path|null $path Текущий Path из route
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function getConstraintsRulesForUpdate(?Path $path): array
    {
        $currentDataType = ($path instanceof Path) ? $path->data_type : null;

        if ($currentDataType === null || !is_string($currentDataType)) {
            // Если data_type не определён, запрещаем constraints
            return ['constraints' => ['prohibited']];
        }

        $registry = $this->getConstraintsRegistry();
        $builder = $registry->getBuilder($currentDataType);

        if ($builder === null) {
            // Если билдер не найден для данного типа данных, запрещаем constraints
            return ['constraints' => ['prohibited']];
        }

        return $builder->buildRulesForUpdate($currentDataType, $path);
    }

    /**
     * Получить кастомные сообщения для ошибок валидации constraints.
     *
     * Собирает сообщения от всех зарегистрированных билдеров.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    protected function getConstraintsValidationMessages(): array
    {
        $registry = $this->getConstraintsRegistry();
        $messages = [];

        // Собираем сообщения от всех зарегистрированных билдеров
        foreach ($registry->getAllBuilders() as $builder) {
            $builderMessages = $builder->buildMessages();
            $messages = array_merge($messages, $builderMessages);
        }

        return $messages;
    }
}
