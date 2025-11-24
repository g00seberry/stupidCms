<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FormPreset\StoreFormConfigRequest;
use App\Http\Resources\Admin\FormConfigResource;
use App\Models\Blueprint;
use App\Models\FormConfig;
use App\Models\PostType;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use App\Support\Http\AdminResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контроллер для управления конфигурацией формы компонентов (FormConfig) в админ-панели.
 *
 * Предоставляет CRUD операции для конфигурации формы:
 * - GET: получение конфигурации по post_type + blueprint
 * - PUT: сохранение/обновление конфигурации
 * - DELETE: удаление конфигурации
 * - GET: список конфигураций по типу контента
 *
 * @group Admin ▸ Form configs
 * @package App\Http\Controllers\Admin\V1
 */
class FormConfigController extends Controller
{
    use ThrowsErrors;

    /**
     * Проверить существование PostType по slug.
     *
     * @param string $postType Slug типа контента
     * @return void
     * @throws \App\Support\Errors\ErrorException Если PostType не найден
     */
    private function ensurePostTypeExists(string $postType): void
    {
        if (! PostType::query()->where('slug', $postType)->exists()) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                "PostType not found: {$postType}",
                ['slug' => $postType]
            );
        }
    }

    /**
     * Получение конфигурации формы по типу контента и blueprint.
     *
     * @group Admin ▸ Form configs
     * @name Get form config
     * @authenticated
     * @urlParam post_type string required Slug типа контента. Example: article
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @response status=200 {
     *   "data": {
     *     "author.contacts.phone": {
     *       "name": "inputText",
     *       "props": {
     *         "label": "Phone",
     *         "placeholder": "Enter phone number"
     *       }
     *     }
     *   }
     * }
     * @response status=200 {
     *   "data": {}
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "PostType not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "PostType not found: article"
     * }
     *
     * @param string $postType Slug типа контента
     * @param \App\Models\Blueprint $blueprint Blueprint (route model binding)
     * @return \Illuminate\Http\JsonResponse|FormConfigResource
     */
    public function show(string $postType, Blueprint $blueprint)
    {
        $this->ensurePostTypeExists($postType);

        $config = FormConfig::query()
            ->where('post_type_slug', $postType)
            ->where('blueprint_id', $blueprint->id)
            ->first();

        // Если конфигурация не найдена, возвращаем пустой объект
        if (! $config) {
            return AdminResponse::json(new \stdClass());
        }

        // Для метода show возвращаем только config_json (объект)
        $configJson = $config->config_json ?? [];
        // Убеждаемся, что возвращается объект, а не массив
        if (is_array($configJson) && ! array_is_list($configJson)) {
            return AdminResponse::json($configJson);
        }
        
        // Если это список или пустой массив, возвращаем пустой объект
        return AdminResponse::json(new \stdClass());
    }

    /**
     * Сохранение или обновление конфигурации формы.
     *
     * @group Admin ▸ Form configs
     * @name Update form config
     * @authenticated
     * @urlParam post_type string required Slug типа контента. Example: article
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @bodyParam config_json object required JSON объект с конфигурацией (ключ - full_path, значение - EditComponent). Example: {"author.contacts.phone":{"name":"inputText","props":{"label":"Phone"}}}
     * @response status=200 {
     *   "data": {
     *     "post_type_slug": "article",
     *     "blueprint_id": 1,
     *     "config_json": {
     *       "author.contacts.phone": {
     *         "name": "inputText",
     *         "props": {
     *           "label": "Phone"
     *         }
     *       }
     *     },
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "PostType not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "PostType not found: article"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The config_json field is required."
     * }
     *
     * @param \App\Http\Requests\Admin\FormPreset\StoreFormConfigRequest $request Валидированный запрос
     * @param string $postType Slug типа контента
     * @param \App\Models\Blueprint $blueprint Blueprint (route model binding)
     * @return FormConfigResource
     */
    public function update(StoreFormConfigRequest $request, string $postType, Blueprint $blueprint): FormConfigResource
    {
        $this->ensurePostTypeExists($postType);

        $validated = $request->validated();
        $configJson = $validated['config_json'] ?? [];

        /** @var FormConfig $config */
        $config = DB::transaction(function () use ($postType, $blueprint, $configJson, $request) {
            $config = FormConfig::query()->updateOrCreate(
                [
                    'post_type_slug' => $postType,
                    'blueprint_id' => $blueprint->id,
                ],
                [
                    'config_json' => $configJson,
                ]
            );

            // Логирование операции
            $isNew = $config->wasRecentlyCreated;
            $action = $isNew ? 'create' : 'update';
            $nodeCount = count(array_keys($configJson));

            Log::info("Form config {$action}d", [
                'action' => $action,
                'post_type_slug' => $postType,
                'blueprint_id' => $blueprint->id,
                'blueprint_code' => $blueprint->code,
                'user_id' => $request->user()?->id,
                'node_count' => $nodeCount,
            ]);

            return $config;
        });

        return new FormConfigResource($config->fresh());
    }

    /**
     * Удаление конфигурации формы.
     *
     * @group Admin ▸ Form configs
     * @name Delete form config
     * @authenticated
     * @urlParam post_type string required Slug типа контента. Example: article
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @response status=204 {}
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Form config not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Form config not found for post_type=article, blueprint_id=1"
     * }
     *
     * @param string $postType Slug типа контента
     * @param \App\Models\Blueprint $blueprint Blueprint (route model binding)
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function destroy(string $postType, Blueprint $blueprint, Request $request): Response
    {
        $this->ensurePostTypeExists($postType);

        $config = FormConfig::query()
            ->where('post_type_slug', $postType)
            ->where('blueprint_id', $blueprint->id)
            ->first();

        if (! $config) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                "Form config not found for post_type={$postType}, blueprint_id={$blueprint->id}",
                [
                    'post_type_slug' => $postType,
                    'blueprint_id' => $blueprint->id,
                ]
            );
        }

        DB::transaction(function () use ($config, $postType, $blueprint, $request) {
            $config->delete();

            // Логирование операции
            Log::info('Form config deleted', [
                'action' => 'delete',
                'post_type_slug' => $postType,
                'blueprint_id' => $blueprint->id,
                'blueprint_code' => $blueprint->code,
                'user_id' => $request->user()?->id,
            ]);
        });

        return AdminResponse::noContent();
    }

    /**
     * Получение списка конфигураций формы для конкретного типа контента.
     *
     * @group Admin ▸ Form configs
     * @name List form configs by post type
     * @authenticated
     * @urlParam post_type string required Slug типа контента. Example: article
     * @response status=200 {
     *   "data": [
     *     {
     *       "post_type_slug": "article",
     *       "blueprint_id": 1,
     *       "config_json": {
     *         "author.contacts.phone": {
     *           "name": "inputText",
     *           "props": {
     *             "label": "Phone"
     *           }
     *         }
     *       },
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:00:00+00:00"
     *     }
     *   ]
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "PostType not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "PostType not found: article"
     * }
     *
     * @param string $postType Slug типа контента
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexByPostType(string $postType): AnonymousResourceCollection
    {
        $this->ensurePostTypeExists($postType);

        $configs = FormConfig::query()
            ->where('post_type_slug', $postType)
            ->with('blueprint')
            ->get();

        return FormConfigResource::collection($configs);
    }
}
