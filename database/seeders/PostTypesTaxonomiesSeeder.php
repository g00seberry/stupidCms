<?php

namespace Database\Seeders;

use App\Models\PostType;
use App\Models\Taxonomy;
use Illuminate\Database\Seeder;

/**
 * Seeder для создания базовых PostTypes и Таксономий
 *
 * Создает:
 * - PostType: 'page'
 * - Taxonomy: 'categories' (hierarchical=1)
 * - Taxonomy: 'tags' (hierarchical=0)
 *
 * ID созданных записей фиксируются в константах класса для дальнейшего использования.
 */
class PostTypesTaxonomiesSeeder extends Seeder
{
    /**
     * ID созданного PostType 'page'
     */
    public static ?int $pagePostTypeId = null;

    /**
     * ID созданной Taxonomy 'categories'
     */
    public static ?int $categoriesTaxonomyId = null;

    /**
     * ID созданной Taxonomy 'tags'
     */
    public static ?int $tagsTaxonomyId = null;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем PostType 'page'
        $pagePostType = PostType::firstOrCreate(
            ['slug' => 'page'],
            [
                'name' => 'Page',
                'options_json' => null,
            ]
        );

        self::$pagePostTypeId = $pagePostType->id;
        if ($this->command) {
            $this->command->info("PostType 'page' created/updated (ID: {$pagePostType->id})");
        }

        // Создаем Taxonomy 'categories' (hierarchical=1)
        $categoriesTaxonomy = Taxonomy::firstOrCreate(
            ['slug' => 'categories'],
            [
                'name' => 'Categories',
                'hierarchical' => true,
            ]
        );

        self::$categoriesTaxonomyId = $categoriesTaxonomy->id;
        if ($this->command) {
            $this->command->info("Taxonomy 'categories' created/updated (ID: {$categoriesTaxonomy->id}, hierarchical: true)");
        }

        // Создаем Taxonomy 'tags' (hierarchical=0)
        $tagsTaxonomy = Taxonomy::firstOrCreate(
            ['slug' => 'tags'],
            [
                'name' => 'Tags',
                'hierarchical' => false,
            ]
        );

        self::$tagsTaxonomyId = $tagsTaxonomy->id;
        if ($this->command) {
            $this->command->info("Taxonomy 'tags' created/updated (ID: {$tagsTaxonomy->id}, hierarchical: false)");

            $this->command->info('');
            $this->command->info('=== Fixed IDs ===');
            $this->command->info("Page PostType ID: " . self::$pagePostTypeId);
            $this->command->info("Categories Taxonomy ID: " . self::$categoriesTaxonomyId);
            $this->command->info("Tags Taxonomy ID: " . self::$tagsTaxonomyId);
        }
    }
}

