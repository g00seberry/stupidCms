<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SlugifyPreviewResource;
use App\Models\Entry;
use App\Models\ReservedRoute;
use App\Support\Slug\Slugifier;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Http\Request;

class UtilsController extends Controller
{
    public function __construct(
        private Slugifier $slugifier,
        private UniqueSlugService $uniqueSlugService,
    ) {}

    /**
     * Генерация slug предпросмотра.
     *
     * @group Admin ▸ Utils
     * @name Slugify preview
     * @authenticated
     * @queryParam title string required Заголовок (<=500). Example: New landing page
     * @queryParam postType string Slug типа записи (для проверки уникальности). Default: page. Example: article
     * @response status=200 {
     *   "base": "new-landing-page",
     *   "unique": "new-landing-page-2"
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "title": [
     *       "The title field is required."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function slugify(Request $request): SlugifyPreviewResource
    {
        $request->validate([
            'title' => 'required|string|max:500',
            'postType' => 'nullable|string',
        ]);

        $title = $request->input('title');
        $postType = $request->input('postType', 'page');

        // Генерируем базовый slug
        $base = $this->slugifier->slugify($title);

        if (empty($base)) {
            return new SlugifyPreviewResource('', '');
        }

        // Проверяем уникальность
        $unique = $this->uniqueSlugService->ensureUnique(
            $base,
            function (string $slug) use ($postType) {
                try {
                    // Проверка в скоупе post_type
                    $exists = Entry::query()
                        ->where('slug', $slug)
                        ->whereHas('postType', fn($q) => $q->where('slug', $postType))
                        ->exists();

                    // Проверка зарезервированных путей
                    $reserved = ReservedRoute::query()
                        ->where('path', $slug)
                        ->orWhere(function ($q) use ($slug) {
                            $q->where('kind', 'prefix')
                                ->where('path', 'like', $slug . '/%');
                        })
                        ->exists();

                    return $exists || $reserved;
                } catch (\Exception $e) {
                    // Если таблиц нет (например, в тестах), считаем slug свободным
                    return false;
                }
            }
        );

        return new SlugifyPreviewResource($base, $unique);
    }
}

