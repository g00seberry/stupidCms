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
 * 4. EntriesSeeder - создает примеры записей контента
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
            EntriesSeeder::class,
        ]);

        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
