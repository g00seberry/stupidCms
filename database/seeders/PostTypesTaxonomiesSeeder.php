<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PostType;
use App\Models\Taxonomy;
use Illuminate\Database\Seeder;

/**
 * Seeder для создания базовых PostTypes и Таксономий.
 *
 * Создает:
 * - PostType: 'page', 'article', 'product'
 * - Taxonomy: 'Categories' (hierarchical=1)
 * - Taxonomy: 'Tags' (hierarchical=0)
 * - Taxonomy: 'Regions' (hierarchical=1)
 * - Taxonomy: 'Topics' (hierarchical=0)
 *
 * ID созданных записей фиксируются в статических свойствах класса для дальнейшего использования.
 */
class PostTypesTaxonomiesSeeder extends Seeder
{
    /**
     * ID созданного PostType 'page'.
     */
    public static ?int $pagePostTypeId = null;

    /**
     * ID созданного PostType 'article'.
     */
    public static ?int $articlePostTypeId = null;

    /**
     * ID созданного PostType 'product'.
     */
    public static ?int $productPostTypeId = null;

    /**
     * ID созданной Taxonomy 'Categories'.
     */
    public static ?int $categoriesTaxonomyId = null;

    /**
     * ID созданной Taxonomy 'Tags'.
     */
    public static ?int $tagsTaxonomyId = null;

    /**
     * ID созданной Taxonomy 'Regions'.
     */
    public static ?int $regionsTaxonomyId = null;

    /**
     * ID созданной Taxonomy 'Topics'.
     */
    public static ?int $topicsTaxonomyId = null;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем PostType 'page'
        $pagePostType = PostType::firstOrCreate(
            ['name' => 'Page'],
            [
                'template' => null,
                'options_json' => null,
            ]
        );

        self::$pagePostTypeId = $pagePostType->id;
        if ($this->command) {
            $this->command->info("PostType 'page' created/updated (ID: {$pagePostType->id})");
        }

        // Создаем PostType 'article'
        $articlePostType = PostType::firstOrCreate(
            ['name' => 'Article'],
            [
                'template' => null,
                'options_json' => [
                    'taxonomies' => [],
                ],
            ]
        );

        self::$articlePostTypeId = $articlePostType->id;
        if ($this->command) {
            $this->command->info("PostType 'article' created/updated (ID: {$articlePostType->id})");
        }

        // Создаем PostType 'product'
        $productPostType = PostType::firstOrCreate(
            ['name' => 'Product'],
            [
                'template' => null,
                'options_json' => [
                    'taxonomies' => [],
                ],
            ]
        );

        self::$productPostTypeId = $productPostType->id;
        if ($this->command) {
            $this->command->info("PostType 'product' created/updated (ID: {$productPostType->id})");
        }

        // Создаем Taxonomy 'Categories' (hierarchical=1)
        $categoriesTaxonomy = Taxonomy::firstOrCreate(
            ['name' => 'Categories'],
            [
                'hierarchical' => true,
                'options_json' => [],
            ]
        );

        self::$categoriesTaxonomyId = $categoriesTaxonomy->id;
        if ($this->command) {
            $this->command->info("Taxonomy 'Categories' created/updated (ID: {$categoriesTaxonomy->id}, hierarchical: true)");
        }

        // Создаем Taxonomy 'Tags' (hierarchical=0)
        $tagsTaxonomy = Taxonomy::firstOrCreate(
            ['name' => 'Tags'],
            [
                'hierarchical' => false,
                'options_json' => [],
            ]
        );

        self::$tagsTaxonomyId = $tagsTaxonomy->id;
        if ($this->command) {
            $this->command->info("Taxonomy 'Tags' created/updated (ID: {$tagsTaxonomy->id}, hierarchical: false)");
        }

        // Создаем Taxonomy 'Regions' (hierarchical=1)
        $regionsTaxonomy = Taxonomy::firstOrCreate(
            ['name' => 'Regions'],
            [
                'hierarchical' => true,
                'options_json' => [],
            ]
        );

        self::$regionsTaxonomyId = $regionsTaxonomy->id;
        if ($this->command) {
            $this->command->info("Taxonomy 'Regions' created/updated (ID: {$regionsTaxonomy->id}, hierarchical: true)");
        }

        // Создаем Taxonomy 'Topics' (hierarchical=0)
        $topicsTaxonomy = Taxonomy::firstOrCreate(
            ['name' => 'Topics'],
            [
                'hierarchical' => false,
                'options_json' => [],
            ]
        );

        self::$topicsTaxonomyId = $topicsTaxonomy->id;
        if ($this->command) {
            $this->command->info("Taxonomy 'Topics' created/updated (ID: {$topicsTaxonomy->id}, hierarchical: false)");

            $this->command->info('');
            $this->command->info('=== Fixed IDs ===');
            $this->command->info("Page PostType ID: " . self::$pagePostTypeId);
            $this->command->info("Article PostType ID: " . self::$articlePostTypeId);
            $this->command->info("Product PostType ID: " . self::$productPostTypeId);
            $this->command->info("Categories Taxonomy ID: " . self::$categoriesTaxonomyId);
            $this->command->info("Tags Taxonomy ID: " . self::$tagsTaxonomyId);
            $this->command->info("Regions Taxonomy ID: " . self::$regionsTaxonomyId);
            $this->command->info("Topics Taxonomy ID: " . self::$topicsTaxonomyId);
        }
    }
}

