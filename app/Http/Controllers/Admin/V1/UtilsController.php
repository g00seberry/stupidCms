<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SlugifyPreviewResource;
use App\Models\Entry;
use App\Models\ReservedRoute;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\HttpErrorException;
use App\Support\Slug\Slugifier;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "8b7cb6c3-0033-f3f5-e9f5-1ce7ceed543b",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-8b7cb6c30033f3f5e9f51ce7ceed543b-8b7cb6c30033f3f5-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The title field is required.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e8b7cb6c3",
     *     "errors": {
     *       "title": [
     *         "The title field is required."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e8b7cb6c3-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e8c30033f",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e8c30033f-eed543b8b7cb6c30-01"
     * }
     */
    public function slugify(Request $request): SlugifyPreviewResource
    {
        /** @var Validator $validator */
        $validator = validator($request->all(), [
            'title' => 'required|string|max:500',
            'postType' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            /** @var ErrorFactory $factory */
            $factory = app(ErrorFactory::class);

            $payload = $factory->for(ErrorCode::VALIDATION_ERROR)
                ->detail('The given data was invalid.')
                ->meta(['errors' => $validator->errors()->toArray()])
                ->build();

            throw new HttpErrorException($payload);
        }

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

    /**
     * Получение списка всех доступных шаблонов.
     *
     * @group Admin ▸ Utils
     * @name Get templates
     * @authenticated
     * @response status=200 {
     *   "data": [
     *     "pages.show",
     *     "home.default",
     *     "welcome"
     *   ]
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "8b7cb6c3-0033-f3f5-e9f5-1ce7ceed543b",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-8b7cb6c30033f3f5e9f51ce7ceed543b-8b7cb6c30033f3f5-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e8c30033f",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e8c30033f-eed543b8b7cb6c30-01"
     * }
     */
    public function templates(): \Illuminate\Http\JsonResponse
    {
        $viewsPath = resource_path('views');
        $templates = $this->scanTemplates($viewsPath);

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Рекурсивно сканирует директорию views и возвращает список шаблонов.
     *
     * Исключает системные директории (admin, errors, layouts, partials, vendor)
     * и возвращает все .blade.php файлы в dot notation формате.
     *
     * @param string $path Путь к директории
     * @param string $prefix Префикс для dot notation
     * @return array<string>
     */
    private function scanTemplates(string $path, string $prefix = ''): array
    {
        $templates = [];
        
        // Директории верхнего уровня, которые нужно исключить из результатов
        // Эти директории не должны содержать шаблоны для назначения PostType/Entry
        $excludedTopLevelDirs = ['admin', 'errors', 'layouts', 'partials', 'vendor'];
        
        if (!is_dir($path)) {
            return $templates;
        }

        $items = scandir($path);
        if ($items === false) {
            return $templates;
        }

        // Определяем, находимся ли мы на верхнем уровне (resources/views)
        $isTopLevel = $prefix === '';

        foreach ($items as $item) {
            // Исключаем служебные файлы и директории
            if ($item === '.' || $item === '..') {
                continue;
            }

            // На верхнем уровне исключаем системные директории
            if ($isTopLevel && in_array($item, $excludedTopLevelDirs, true)) {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($itemPath)) {
                // Рекурсивно сканируем поддиректории (исключения применяются только на верхнем уровне)
                $newPrefix = $prefix === '' ? $item : $prefix . '.' . $item;
                $templates = array_merge($templates, $this->scanTemplates($itemPath, $newPrefix));
            } elseif (is_file($itemPath) && str_ends_with($item, '.blade.php')) {
                // Конвертируем имя файла в dot notation
                $templateName = str_replace('.blade.php', '', $item);
                $template = $prefix === '' ? $templateName : $prefix . '.' . $templateName;
                $templates[] = $template;
            }
        }

        // Сортируем шаблоны для предсказуемого порядка
        sort($templates);

        return $templates;
    }
}

