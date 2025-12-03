<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Concerns;

use App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface;
use App\Domain\Blueprint\Validation\EntryValidationServiceInterface;
use App\Models\PostType;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

/**
 * Trait для добавления валидации Blueprint в Request классы.
 *
 * @package App\Http\Requests\Admin\Concerns
 */
trait BlueprintValidationTrait
{
    /**
     * Добавить правила валидации для content_json из Blueprint.
     *
     * Использует доменный сервис EntryValidationService для построения RuleSet
     * и адаптер LaravelValidationAdapter для преобразования в Laravel правила.
     * Не добавляет правила автоматически - пользователь сам настраивает все правила.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @param \App\Models\PostType|null $postType PostType для получения blueprint (если null, будет получен из запроса)
     * @return void
     */
    protected function addBlueprintValidationRules(Validator $validator, ?PostType $postType = null): void
    {
        // Получаем PostType, если не передан
        if ($postType === null) {
            $postTypeSlug = $this->input('post_type');
            if (! $postTypeSlug) {
                return;
            }

            $postType = PostType::query()
                ->with('blueprint')
                ->where('slug', $postTypeSlug)
                ->first();
        }

        if (! $postType || ! $postType->blueprint) {
            return;
        }

        // Используем доменный сервис для построения RuleSet
        $validationService = app(EntryValidationServiceInterface::class);
        $ruleSet = $validationService->buildRulesFor($postType->blueprint);

        if ($ruleSet->isEmpty()) {
            return;
        }

        // Адаптируем RuleSet в Laravel правила
        $adapter = app(LaravelValidationAdapterInterface::class);
        $laravelRules = $adapter->adapt($ruleSet);

        Log::debug('Blueprint validation rules adapted', [
            'laravelRules' => $laravelRules,
          
        ]);

        // Добавляем все правила в валидатор
        foreach ($laravelRules as $field => $rules) {
            $validator->addRules([$field => $rules]);
        }
    }

}

