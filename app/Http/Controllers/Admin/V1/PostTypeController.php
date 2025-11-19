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
     * @bodyParam slug string required Уникальный slug PostType. Example: product
     * @bodyParam name string required Человекочитаемое название. Example: Products
     * @bodyParam options_json object Опциональные настройки. Example: {"fields":{"price":{"type":"number"}}}
     * @response status=201 {
     *   "data": {
     *     "id": 1,
     *     "slug": "product",
     *     "name": "Products",
     *     "options_json": {
     *       "fields": {
     *         "price": {
     *           "type": "number"
     *         }
     *       }
     *     },
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
     *   "detail": "The slug has already been taken.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555556",
     *     "errors": {
     *       "slug": [
     *         "The slug has already been taken."
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
            $slug = strtolower(trim($validated['slug']));

            return PostType::query()->create([
                'slug' => $slug,
                'name' => $validated['name'],
                'options_json' => $options,
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
     *       "slug": "article",
     *       "name": "Articles",
     *       "options_json": {},
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
            ->orderBy('slug')
            ->get();

        return PostTypeResource::collection($types);
    }

    /**
     * Получение настроек типа записи.
     *
     * @group Admin ▸ Post types
     * @name Show post type
     * @authenticated
     * @urlParam slug string required Slug PostType. Example: article
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "slug": "article",
     *     "name": "Articles",
     *     "options_json": {},
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
     *   "detail": "Unknown post type slug: article",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555556",
     *     "slug": "article"
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
    public function show(string $slug): PostTypeResource
    {
        $type = PostType::query()->where('slug', $slug)->first();

        if (! $type) {
            $this->throwPostTypeNotFound($slug);
        }

        return new PostTypeResource($type);
    }

    private function throwPostTypeNotFound(string $slug): never
    {
        $this->throwError(
            ErrorCode::NOT_FOUND,
            sprintf('Unknown post type slug: %s', $slug),
            ['slug' => $slug],
        );
    }

    /**
     * Обновление настроек типа записи.
     *
     * Обновляет slug, name и options_json типа записи. Нормализует taxonomies в options_json:
     * принимает как целые числа, так и строковые представления чисел, преобразуя их в целые числа.
     *
     * @param \App\Http\Requests\Admin\UpdatePostTypeRequest $request Валидированный запрос
     * @param string $slug Slug типа записи для обновления
     * @return \App\Http\Resources\Admin\PostTypeResource Обновлённый тип записи
     * @throws \App\Support\Errors\HttpErrorException Если тип записи не найден (ErrorCode::NOT_FOUND)
     *
     * @group Admin ▸ Post types
     * @name Update post type
     * @authenticated
     * @urlParam slug string required Slug PostType. Example: article
     * @bodyParam slug string optional Новый slug PostType. Example: article-updated
     * @bodyParam name string optional Человекочитаемое название. Example: Articles Updated
     * @bodyParam options_json object required JSON-объект схемы настроек. Example: {"fields":{"hero":{"type":"image"}}}
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "slug": "article",
     *     "name": "Articles",
     *     "options_json": {
     *       "fields": {
     *         "hero": {
     *           "type": "image"
     *         }
     *       }
     *     },
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
     *   "detail": "Unknown post type slug: article",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555558",
     *     "slug": "article"
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
    public function update(UpdatePostTypeRequest $request, string $slug): PostTypeResource
    {
        $type = PostType::query()->where('slug', $slug)->first();

        if (! $type) {
            $this->throwPostTypeNotFound($slug);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($type, $validated) {
            if (isset($validated['slug'])) {
                $type->slug = strtolower(trim($validated['slug']));
            }
            if (isset($validated['name'])) {
                $type->name = $validated['name'];
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
     * @urlParam slug string required Slug PostType. Example: article
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
     *   "detail": "Unknown post type slug: article",
     *   "meta": {
     *     "request_id": "41111111-2222-3333-4444-555555555561",
     *     "slug": "article"
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
    public function destroy(Request $request, string $slug): Response
    {
        $type = PostType::query()->where('slug', $slug)->first();

        if (! $type) {
            $this->throwPostTypeNotFound($slug);
        }

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

