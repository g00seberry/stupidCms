<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Главный сидер базы данных.
 *
 * Вызывает все необходимые сидеры в правильном порядке:
 * 1. AdminUserSeeder - создает административного пользователя
 * 2. PostTypesTaxonomiesSeeder - создает типы постов и таксономии
 * 3. TermsSeeder - создает термы для всех таксономий
 * 4. BlueprintsSeeder - создает примеры Blueprint с различной сложностью
 * 5. EntriesSeeder - создает примеры записей контента (без blueprint)
 * 6. BlueprintEntriesSeeder - создает примеры записей с использованием Blueprint
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            PostTypesTaxonomiesSeeder::class,
            TermsSeeder::class,
            BlueprintsSeeder::class,
            EntriesSeeder::class,
            BlueprintEntriesSeeder::class,
        ]);

        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
