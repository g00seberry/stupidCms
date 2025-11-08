<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\UpdatePostTypeRequest;
use App\Http\Resources\Admin\PostTypeResource;
use App\Models\PostType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PostTypeController extends Controller
{
    use Problems;

    /**
     * Display the specified post type.
     */
    public function show(string $slug): JsonResponse
    {
        $type = PostType::query()->where('slug', $slug)->first();

        if (!$type) {
            return $this->problem(
                status: 404,
                title: 'PostType not found',
                detail: "Unknown post type slug: {$slug}",
                ext: ['type' => 'https://stupidcms.dev/problems/not-found'],
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            );
        }

        $resource = new PostTypeResource($type);
        $response = $resource->toResponse(request());
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Vary', 'Cookie');

        return $response;
    }

    /**
     * Update the specified post type.
     */
    public function update(UpdatePostTypeRequest $request, string $slug): JsonResponse
    {
        $type = PostType::query()->where('slug', $slug)->first();

        if (!$type) {
            return $this->problem(
                status: 404,
                title: 'PostType not found',
                detail: "Unknown post type slug: {$slug}",
                ext: ['type' => 'https://stupidcms.dev/problems/not-found'],
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            );
        }

        DB::transaction(function () use ($type, $request) {
            $type->options_json = $request->validated('options_json');
            $type->save();
        });

        $type->refresh();

        $resource = new PostTypeResource($type);
        $response = $resource->toResponse(request());
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Vary', 'Cookie');

        return $response;
    }
}

