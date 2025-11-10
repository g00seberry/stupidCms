<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\UpdatePostTypeRequest;
use App\Http\Resources\Admin\PostTypeResource;
use App\Models\PostType;
use App\Support\Http\Problems\PostTypeNotFoundProblem;
use Illuminate\Support\Facades\DB;

class PostTypeController extends Controller
{
    use Problems;

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
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "PostType not found",
     *   "status": 404,
     *   "detail": "Unknown post type slug: article"
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function show(string $slug): PostTypeResource
    {
        $type = PostType::query()->where('slug', $slug)->first();

        if (! $type) {
            throw new PostTypeNotFoundProblem($slug);
        }

        return new PostTypeResource($type);
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
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "PostType not found",
     *   "status": 404,
     *   "detail": "Unknown post type slug: article"
     * }
     * @response status=422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "options_json": [
     *       "The options_json field is required."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function update(UpdatePostTypeRequest $request, string $slug): PostTypeResource
    {
        $type = PostType::query()->where('slug', $slug)->first();

        if (! $type) {
            throw new PostTypeNotFoundProblem($slug);
        }

        DB::transaction(function () use ($type, $request) {
            $type->options_json = $request->validated('options_json');
            $type->save();
        });

        $type->refresh();

        return new PostTypeResource($type);
    }

}

