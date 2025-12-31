<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path\Constraints;

use App\Models\Path;

/**
 * Интерфейс для билдеров правил валидации constraints Path.
 *
 * Каждый билдер отвечает за построение правил валидации для определённого типа данных.
 * Например, RefConstraintsValidationBuilder строит правила для data_type='ref',
 * а MediaConstraintsValidationBuilder - для data_type='media'.
 *
 * @package App\Http\Requests\Admin\Path\Constraints
 */
interface ConstraintsValidationBuilderInterface
{
    /**
     * Получить правила валидации для constraints при создании Path (StorePathRequest).
     *
     * Правила должны быть построены на основе переданного data_type.
     * Если data_type не соответствует поддерживаемому типу билдера,
     * должны возвращаться правила, запрещающие использование constraints.
     *
     * @param string $dataType Тип данных поля Path (string, text, int, float, bool, datetime, json, ref, media)
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> Правила валидации Laravel
     */
    public function buildRulesForStore(string $dataType): array;

    /**
     * Получить правила валидации для constraints при обновлении Path (UpdatePathRequest).
     *
     * Правила должны учитывать текущий data_type из модели Path,
     * так как data_type нельзя изменять после создания.
     *
     * @param string $dataType Текущий тип данных поля Path из модели
     * @param Path|null $path Текущий Path из route (может быть null в некоторых случаях)
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> Правила валидации Laravel
     */
    public function buildRulesForUpdate(string $dataType, ?Path $path): array;

    /**
     * Получить кастомные сообщения для ошибок валидации constraints.
     *
     * Сообщения должны быть на русском языке и соответствовать правилам,
     * возвращаемым методами buildRulesForStore() и buildRulesForUpdate().
     *
     * @return array<string, string> Массив сообщений об ошибках в формате 'field.rule' => 'Сообщение'
     */
    public function buildMessages(): array;

    /**
     * Получить тип данных, который поддерживает этот билдер.
     *
     * Билдер должен обрабатывать только constraints для указанного типа данных.
     * Например, RefConstraintsValidationBuilder возвращает 'ref',
     * а MediaConstraintsValidationBuilder возвращает 'media'.
     *
     * @return string Тип данных (например, 'ref', 'media')
     */
    public function getSupportedDataType(): string;
}

