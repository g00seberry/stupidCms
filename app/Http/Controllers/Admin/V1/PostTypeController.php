<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\UpdatePostTypeRequest;
use App\Http\Resources\Admin\PostTypeResource;
use App\Models\PostType;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class PostTypeController extends Controller
{
    use Problems;

    /**
     * Display the specified post type.
     */
    public function show(string $slug): PostTypeResource
    {
        $type = PostType::query()->where('slug', $slug)->first();

        if (! $type) {
            $this->throwNotFound($slug);
        }

        return new PostTypeResource($type);
    }

    /**
     * Update the specified post type.
     */
    public function update(UpdatePostTypeRequest $request, string $slug): PostTypeResource
    {
        $type = PostType::query()->where('slug', $slug)->first();

        if (! $type) {
            $this->throwNotFound($slug);
        }

        DB::transaction(function () use ($type, $request) {
            $type->options_json = $request->validated('options_json');
            $type->save();
        });

        $type->refresh();

        return new PostTypeResource($type);
    }

    private function throwNotFound(string $slug): never
    {
        throw new HttpResponseException(
            $this->problem(
                status: 404,
                title: 'PostType not found',
                detail: "Unknown post type slug: {$slug}",
                ext: ['type' => 'https://stupidcms.dev/problems/not-found'],
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            )
        );
    }
}

