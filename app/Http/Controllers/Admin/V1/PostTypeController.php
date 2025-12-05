<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\PostTypes\PostTypeOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePostTypeRequest;
use App\Http\Requests\Admin\UpdatePostTypeRequest;
use App\Http\Resources\Admin\PostTypeResource;
use App\Models\Entry;
use App\Models\PostType;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use App\Support\Http\AdminResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контроллер для управления типами записей (PostType) в админ-панели.
 *
 * Предоставляет CRUD операции для типов записей: создание, чтение, обновление, удаление.
 * Управляет настройками типов записей через options_json.
 *
 * @package App\Http\Controllers\Admin\V1
 */
class PostTypeController extends Controller
{
    use ThrowsErrors;

    /**
     * Создание нового типа записи.
     *
     * @group Admin ▸ Post types
     * @name Create post type
     * @authenticated
     * @bodyParam name string required Человекочитаемое название. Example: Products
     * @bodyParam template string optional Путь к шаблону (должен быть в папке templates). Example: templates.article
     * @bodyParam options_json object Опциональные настройки. Example: {"fields":{"price":{"type":"number"}}}
     * @bodyParam blueprint_id integer optional ID Blueprint для привязки к типу записи. Example: 1
     * @response status=201 {
     *   "data": {
     *     "id": 1,
     *     "name": "Products",
     *     "template": "templates.article",
     *     "options_json": {
     *       "fields": {
     *         "price": {
     *           "type": "number"
     *         }
     *       }
     *     },
     *     "blueprint_id": null,
     *     "created_at": "2025-01-10T12:45:00+00:00",
     *     "updated_at": "2025-01-10T12:45:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555555",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-51111111222233334444555555555555-5111111122223333-01"
     * }
     * @response status=403 {
     *   "type": "https://stupidcms.dev/problems/forbidden",
     *   "title": "Forbidden",
     *   "status": 403,
     *   "code": "FORBIDDEN",
     *   "detail": "This action is unauthorized."
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The template field is invalid.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555556",
     *     "errors": {
     *       "template": [
     *         "The template must be a path within the templates directory."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-51111111222233334444555555555556-5111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "56666666-7777-8888-9999-000000000000",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-56666666777788889999000000000000-5666666677778888-01"
     * }
     */
    public function store(StorePostTypeRequest $request): PostTypeResource
    {
        $validated = $request->validated();

        $optionsData = $validated['options_json'] ?? [];
        $options = PostTypeOptions::fromArray($optionsData);

        /** @var PostType $postType */
        $postType = DB::transaction(function () use ($validated, $options) {
            return PostType::query()->create([
                'name' => $validated['name'],
                'template' => $validated['template'] ?? null,
                'options_json' => $options,
                'blueprint_id' => $validated['blueprint_id'] ?? null,
            ]);
        });

        $warnings = $request->warnings();
        return new PostTypeResource($postType->fresh(), true, $warnings);
    }

    /**
     * Список всех типов записей.
     *
     * @group Admin ▸ Post types
     * @name List post types
     * @authenticated
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Articles",
     *       "template": "templates.article",
     *       "options_json": {},
     *       "blueprint_id": null,
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:45:00+00:00"
     *     }
     *   ]
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555555",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-41111111222233334444555555555555-4111111122223333-01"
     * }
     * @response status=403 {
     *   "type": "https://stupidcms.dev/problems/forbidden",
     *   "title": "Forbidden",
     *   "status": 403,
     *   "code": "FORBIDDEN",
     *   "detail": "This action is unauthorized."
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "46666666-7777-8888-9999-000000000000",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-46666666777788889999000000000000-4666666677778888-01"
     * }
     */
    public function index(): AnonymousResourceCollection
    {
        $types = PostType::query()
            ->orderBy('name')
            ->get();

        return PostTypeResource::collection($types);
    }

    /**
     * Получение настроек типа записи.
     *
     * @group Admin ▸ Post types
     * @name Show post type
     * @authenticated
     * @urlParam id int required ID PostType. Example: 1
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Articles",
     *     "template": "templates.article",
     *     "options_json": {},
     *     "blueprint_id": null,
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:45:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555555",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-41111111222233334444555555555555-4111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "PostType not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "PostType not found: 1",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555556",
     *     "id": 1
     *   },
     *   "trace_id": "00-41111111222233334444555555555556-4111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "46666666-7777-8888-9999-000000000000",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-46666666777788889999000000000000-4666666677778888-01"
     * }
     */
    public function show(int $id): PostTypeResource
    {
        $type = PostType::findOrFail($id);

        return new PostTypeResource($type);
    }

    /**
     * Обновление настроек типа записи.
     *
     * Обновляет name, template, options_json и blueprint_id типа записи. Нормализует taxonomies в options_json:
     * принимает как целые числа, так и строковые представления чисел, преобразуя их в целые числа.
     *
     * @param \App\Http\Requests\Admin\UpdatePostTypeRequest $request Валидированный запрос
     * @param int $id ID типа записи для обновления
     * @return \App\Http\Resources\Admin\PostTypeResource Обновлённый тип записи
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Если тип записи не найден (автоматически преобразуется в 404)
     *
     * @group Admin ▸ Post types
     * @name Update post type
     * @authenticated
     * @urlParam id int required ID PostType. Example: 1
     * @bodyParam name string optional Человекочитаемое название. Example: Articles Updated
     * @bodyParam template string optional Путь к шаблону (должен быть в папке templates). Example: templates.article
     * @bodyParam options_json object required JSON-объект схемы настроек. Example: {"fields":{"hero":{"type":"image"}}}
     * @bodyParam blueprint_id integer optional ID Blueprint для привязки к типу записи. Example: 1
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Articles",
     *     "template": "templates.article",
     *     "options_json": {
     *       "fields": {
     *         "hero": {
     *           "type": "image"
     *         }
     *       }
     *     },
     *     "blueprint_id": null,
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:45:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555557",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-41111111222233334444555555555557-4111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "PostType not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "PostType not found: 1",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555558",
     *     "id": 1
     *   },
     *   "trace_id": "00-41111111222233334444555555555558-4111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The options_json field is required.",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555559",
     *     "errors": {
     *       "options_json": [
     *         "The options_json field is required."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-41111111222233334444555555555559-4111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "46666666-7777-8888-9999-000000000001",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-46666666777788889999000000000001-4666666677778888-01"
     * }
     */
    public function update(UpdatePostTypeRequest $request, int $id): PostTypeResource
    {
        $type = PostType::findOrFail($id);

        $validated = $request->validated();

        DB::transaction(function () use ($type, $validated) {
            if (isset($validated['name'])) {
                $type->name = $validated['name'];
            }
            if (array_key_exists('template', $validated)) {
                $type->template = $validated['template'];
            }
            if (array_key_exists('blueprint_id', $validated)) {
                $type->blueprint_id = $validated['blueprint_id'];
            }
            $options = PostTypeOptions::fromArray($validated['options_json']);
            $type->options_json = $options;
            $type->save();
        });

        $type->refresh();

        $warnings = $request->warnings();
        return new PostTypeResource($type, false, $warnings);
    }

    /**
     * Удаление типа записи.
     *
     * @group Admin ▸ Post types
     * @name Delete post type
     * @authenticated
     * @urlParam id int required ID PostType. Example: 1
     * @queryParam force boolean Каскадно удалить все записи (Entry) этого типа. Example: true
     * @response status=204 {}
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555560",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-41111111222233334444555555555560-4111111122223333-01"
     * }
     * @response status=403 {
     *   "type": "https://stupidcms.dev/problems/forbidden",
     *   "title": "Forbidden",
     *   "status": 403,
     *   "code": "FORBIDDEN",
     *   "detail": "This action is unauthorized."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "PostType not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "PostType not found: 1",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555561",
     *     "id": 1
     *   },
     *   "trace_id": "00-41111111222233334444555555555561-4111111122223333-01"
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/conflict",
     *   "title": "PostType has entries",
     *   "status": 409,
     *   "code": "CONFLICT",
     *   "detail": "Cannot delete post type while entries exist. Use force=1 to cascade delete.",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555562",
     *     "entries_count": 5
     *   },
     *   "trace_id": "00-41111111222233334444555555555562-4111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "46666666-7777-8888-9999-000000000002",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-46666666777788889999000000000002-4666666677778888-01"
     * }
     */
    public function destroy(Request $request, int $id): Response
    {
        $type = PostType::findOrFail($id);

        $force = $request->boolean('force');

        // Проверяем наличие связанных Entry (включая soft-deleted)
        $entriesCount = Entry::query()
            ->withTrashed()
            ->where('post_type_id', $type->id)
            ->count();

        if ($entriesCount > 0 && ! $force) {
            $this->throwError(
                ErrorCode::CONFLICT,
                'Cannot delete post type while entries exist. Use force=1 to cascade delete.',
                ['entries_count' => $entriesCount],
            );
        }

        DB::transaction(function () use ($type, $force) {
            if ($force) {
                // Каскадно удаляем все Entry (включая soft-deleted)
                // Связи (entry_term) удаляются автоматически через каскад в БД
                Entry::query()
                    ->withTrashed()
                    ->where('post_type_id', $type->id)
                    ->each(function (Entry $entry): void {
                        $entry->forceDelete();
                    });
            }

            $type->delete();
        });

        return AdminResponse::noContent();
    }

}

