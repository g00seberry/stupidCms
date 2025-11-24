<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Blueprint;
use App\Models\FormConfig;
use App\Models\PostType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FormConfig>
 */
class FormConfigFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FormConfig::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_type_slug' => PostType::factory()->create()->slug,
            'blueprint_id' => Blueprint::factory(),
            'config_json' => [],
        ];
    }

    /**
     * Установить конфигурацию формы.
     *
     * @param array<string, mixed> $config Конфигурация (ключ - full_path, значение - EditComponent)
     * @return static
     */
    public function withConfig(array $config): static
    {
        return $this->state(fn (array $attributes) => [
            'config_json' => $config,
        ]);
    }
}
