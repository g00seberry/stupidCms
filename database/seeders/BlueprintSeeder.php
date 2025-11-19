<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Blueprint;
use App\Models\Path;
use App\Models\PostType;
use Illuminate\Database\Seeder;

class BlueprintSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Компонент: SEO Fields
        $seoComponent = Blueprint::create([
            'slug' => 'seo_fields',
            'name' => 'SEO Fields',
            'description' => 'SEO метаданные',
            'type' => 'component',
            'post_type_id' => null,
        ]);

        Path::create([
            'blueprint_id' => $seoComponent->id,
            'name' => 'metaTitle',
            'full_path' => 'metaTitle',
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_indexed' => true,
            'is_required' => false,
        ]);

        Path::create([
            'blueprint_id' => $seoComponent->id,
            'name' => 'metaDescription',
            'full_path' => 'metaDescription',
            'data_type' => 'text',
            'cardinality' => 'one',
            'is_indexed' => false,
            'is_required' => false,
        ]);

        // Компонент: Author Info
        $authorComponent = Blueprint::create([
            'slug' => 'author_info',
            'name' => 'Author Info',
            'description' => 'Информация об авторе',
            'type' => 'component',
            'post_type_id' => null,
        ]);

        Path::create([
            'blueprint_id' => $authorComponent->id,
            'name' => 'name',
            'full_path' => 'name',
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_indexed' => true,
        ]);

        // Full Blueprint: Article (если PostType существует)
        $articlePostType = PostType::where('slug', 'article')->first();
        if ($articlePostType) {
            $articleBlueprint = Blueprint::create([
                'post_type_id' => $articlePostType->id,
                'slug' => 'article_full',
                'name' => 'Article Full',
                'description' => 'Полная схема статьи',
                'type' => 'full',
                'is_default' => true,
            ]);

            Path::create([
                'blueprint_id' => $articleBlueprint->id,
                'name' => 'content',
                'full_path' => 'content',
                'data_type' => 'text',
                'cardinality' => 'one',
                'is_indexed' => false,
            ]);

            Path::create([
                'blueprint_id' => $articleBlueprint->id,
                'name' => 'relatedArticles',
                'full_path' => 'relatedArticles',
                'data_type' => 'ref',
                'cardinality' => 'many',
                'is_indexed' => true,
                'ref_target_type' => 'article',
            ]);

            // TODO: Migrate to embedded blueprints (data_type='blueprint')
            // For now, seeder works only with direct paths
        }
    }
}

