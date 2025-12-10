<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\RouteNode;
use Illuminate\Database\Seeder;

/**
 * Seeder для создания примеров узлов маршрутов.
 *
 * Создает примеры дерева маршрутов:
 * - Корневая группа `blog` с дочерним маршрутом `{slug}`
 * - Статический маршрут `about` с `action_type=entry`
 * - Redirect через `action_type=CONTROLLER` с `action='redirect:/new-page:301'`
 */
class RouteNodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Пример 1: Корневая группа `blog` с дочерним маршрутом `{slug}`
        $blogGroup = RouteNode::firstOrCreate(
            [
                'kind' => RouteNodeKind::GROUP,
                'prefix' => 'blog',
            ],
            [
                'parent_id' => null,
                'sort_order' => 0,
                'enabled' => true,
                'kind' => RouteNodeKind::GROUP,
                'name' => null,
                'domain' => null,
                'prefix' => 'blog',
                'namespace' => null,
                'methods' => null,
                'uri' => null,
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action' => null,
                'entry_id' => null,
                'middleware' => ['web'],
                'where' => null,
                'defaults' => null,
                'options' => null,
            ]
        );

        RouteNode::firstOrCreate(
            [
                'parent_id' => $blogGroup->id,
                'uri' => '{slug}',
            ],
            [
                'parent_id' => $blogGroup->id,
                'sort_order' => 0,
                'enabled' => true,
                'kind' => RouteNodeKind::ROUTE,
                'name' => 'blog.show',
                'domain' => null,
                'prefix' => null,
                'namespace' => null,
                'methods' => ['GET'],
                'uri' => '{slug}',
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action' => 'App\\Http\\Controllers\\BlogController@show',
                'entry_id' => null,
                'middleware' => null,
                'where' => ['slug' => '[a-z0-9-]+'],
                'defaults' => null,
                'options' => null,
            ]
        );

        // Пример 2: Статический маршрут `about` с `action_type=entry`
        $pagePostType = PostType::where('name', 'Page')->first();
        if ($pagePostType) {
            $aboutEntry = Entry::where('post_type_id', $pagePostType->id)
                ->where('title', 'About Us')
                ->first();

            if ($aboutEntry) {
                RouteNode::firstOrCreate(
                    [
                        'uri' => 'about',
                    ],
                    [
                        'parent_id' => null,
                        'sort_order' => 1,
                        'enabled' => true,
                        'kind' => RouteNodeKind::ROUTE,
                        'name' => 'about',
                        'domain' => null,
                        'prefix' => null,
                        'namespace' => null,
                        'methods' => ['GET'],
                        'uri' => 'about',
                        'action_type' => RouteNodeActionType::ENTRY,
                        'action' => null,
                        'entry_id' => $aboutEntry->id,
                        'middleware' => ['web'],
                        'where' => null,
                        'defaults' => null,
                        'options' => null,
                    ]
                );
            }
        }

        // Пример 3: Redirect через `action_type=CONTROLLER` с `action='redirect:/new-page:301'`
        RouteNode::firstOrCreate(
            [
                'uri' => 'old-page',
            ],
            [
                'parent_id' => null,
                'sort_order' => 2,
                'enabled' => true,
                'kind' => RouteNodeKind::ROUTE,
                'name' => null,
                'domain' => null,
                'prefix' => null,
                'namespace' => null,
                'methods' => ['GET'],
                'uri' => 'old-page',
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action' => 'redirect:/new-page:301',
                'entry_id' => null,
                'middleware' => null,
                'where' => null,
                'defaults' => null,
                'options' => null,
            ]
        );

        if ($this->command) {
            $this->command->info('Route nodes created successfully!');
            $this->command->info('Total route nodes: ' . RouteNode::count());
            $this->command->info('Groups: ' . RouteNode::where('kind', RouteNodeKind::GROUP)->count());
            $this->command->info('Routes: ' . RouteNode::where('kind', RouteNodeKind::ROUTE)->count());
        }
    }
}
