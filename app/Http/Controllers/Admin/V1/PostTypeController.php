<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePostTypeRequest;
use App\Http\Resources\Admin\PostTypeResource;
use App\Models\PostType;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use Illuminate\Support\Facades\DB;

class PostTypeController extends Controller
{
    use ThrowsErrors;

    /**
     * Получение настроек типа записи.
     *
     * @group Admin ▸ Post types
     * @name Show post type
     * @authenticated
     * @urlParam slug string required Slug PostType. Example: article
     * @response status=200 {
     *   "data": {
     *     "slug": "article",
     *     "label": "Articles",
     *     "options_json": {},
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
     * @group Admin ▸ Post types
     * @name Update post type
     * @authenticated
     * @urlParam slug string required Slug PostType. Example: article
     * @bodyParam options_json object required JSON-объект схемы настроек. Example: {"fields":{"hero":{"type":"image"}}}
     * @response status=200 {
     *   "data": {
     *     "slug": "article",
     *     "label": "Articles",
     *     "options_json": {
     *       "fields": {
     *         "hero": {
     *           "type": "image"
     *         }
     *       }
     *     },
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

        DB::transaction(function () use ($type, $request) {
            $type->options_json = $request->validated('options_json');
            $type->save();
        });

        $type->refresh();

        return new PostTypeResource($type);
    }

}

