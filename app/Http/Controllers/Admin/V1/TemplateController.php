<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTemplateRequest;
use App\Http\Requests\Admin\UpdateTemplateRequest;
use App\Http\Resources\Admin\TemplateResource;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class TemplateController extends Controller
{
    use ThrowsErrors;
    /**
     * Получение списка всех доступных шаблонов.
     *
     * @group Admin ▸ Templates
     * @name Get templates
     * @authenticated
     * @response status=200 {
     *   "data": [
     *     {
     *       "name": "pages.show",
     *       "path": "pages/show.blade.php",
     *       "exists": true
     *     },
     *     {
     *       "name": "home.default",
     *       "path": "home/default.blade.php",
     *       "exists": true
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
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e8b7cb6c3",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e8b7cb6c3-eed543b8b7cb6c30-01"
     * }
     */
    public function index(): AnonymousResourceCollection
    {
        $viewsPath = resource_path('views');
        $templates = $this->scanTemplates($viewsPath);

        $templatesData = array_map(function (string $templateName) {
            $path = $this->templateNameToPath($templateName);
            return [
                'name' => $templateName,
                'path' => $path,
                'exists' => View::exists($templateName),
            ];
        }, $templates);

        return TemplateResource::collection($templatesData);
    }

    /**
     * Получение конкретного шаблона по имени.
     *
     * @group Admin ▸ Templates
     * @name Get template
     * @authenticated
     * @urlParam name string required Имя шаблона в dot notation. Example: pages.article
     * @response status=200 {
     *   "data": {
     *     "name": "pages.article",
     *     "path": "pages/article.blade.php",
     *     "exists": true,
     *     "content": "<div>Template content</div>",
     *     "created_at": null,
     *     "updated_at": null
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Not Found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Template not found."
     * }
     */
    public function show(string $name): TemplateResource
    {
        $filePath = $this->getTemplateFilePath($name);

        // Проверяем, что файл существует
        if (!File::exists($filePath)) {
            $this->throwError(ErrorCode::NOT_FOUND, 'Template not found.');
        }

        // Читаем содержимое файла
        $content = File::get($filePath);

        // Получаем информацию о файле
        $fileInfo = [
            'name' => $name,
            'path' => $this->templateNameToPath($name),
            'exists' => true,
            'content' => $content,
        ];

        // Добавляем даты изменения файла, если доступны
        $modifiedTime = File::lastModified($filePath);
        if ($modifiedTime !== false) {
            $fileInfo['updated_at'] = date('c', $modifiedTime);
        }

        return new TemplateResource($fileInfo);
    }

    /**
     * Создание нового шаблона.
     *
     * @group Admin ▸ Templates
     * @name Create template
     * @authenticated
     * @bodyParam name string required Имя шаблона в dot notation. Example: pages.article
     * @bodyParam content string required Содержимое шаблона. Example: <div>Hello</div>
     * @response status=201 {
     *   "data": {
     *     "name": "pages.article",
     *     "path": "pages/article.blade.php",
     *     "exists": true,
     *     "created_at": "2025-01-10T12:45:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/conflict",
     *   "title": "Conflict",
     *   "status": 409,
     *   "code": "CONFLICT",
     *   "detail": "Template already exists."
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The name field is required."
     * }
     */
    public function store(StoreTemplateRequest $request): TemplateResource
    {
        $validated = $request->validated();
        $name = $validated['name'];
        $content = $validated['content'];

        $filePath = $this->getTemplateFilePath($name);

        // Проверяем, что файл не существует
        if (File::exists($filePath)) {
            $this->throwError(ErrorCode::CONFLICT, 'Template already exists.');
        }

        // Создаём директории, если их нет
        $directory = dirname($filePath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Записываем файл
        File::put($filePath, $content);

        return new TemplateResource([
            'name' => $name,
            'path' => $this->templateNameToPath($name),
            'exists' => true,
            'created_at' => now()->toIso8601String(),
        ], true);
    }

    /**
     * Обновление существующего шаблона.
     *
     * @group Admin ▸ Templates
     * @name Update template
     * @authenticated
     * @urlParam name string required Имя шаблона в dot notation. Example: pages.article
     * @bodyParam content string required Содержимое шаблона. Example: <div>Updated</div>
     * @response status=200 {
     *   "data": {
     *     "name": "pages.article",
     *     "path": "pages/article.blade.php",
     *     "exists": true,
     *     "updated_at": "2025-01-10T12:45:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Not Found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Template not found."
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The content field is required."
     * }
     */
    public function update(UpdateTemplateRequest $request, string $name): TemplateResource
    {
        $validated = $request->validated();
        $content = $validated['content'];

        $filePath = $this->getTemplateFilePath($name);

        // Проверяем, что файл существует
        if (!File::exists($filePath)) {
            $this->throwError(ErrorCode::NOT_FOUND, 'Template not found.');
        }

        // Записываем обновлённое содержимое
        File::put($filePath, $content);

        return new TemplateResource([
            'name' => $name,
            'path' => $this->templateNameToPath($name),
            'exists' => true,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Преобразует имя шаблона в dot notation в путь к файлу.
     *
     * @param string $name Имя шаблона (например, "pages.article")
     * @return string Путь к файлу (например, "pages/article.blade.php")
     */
    private function templateNameToPath(string $name): string
    {
        $parts = explode('.', $name);
        $fileName = array_pop($parts);
        $directory = implode('/', $parts);

        if ($directory === '') {
            return $fileName . '.blade.php';
        }

        return $directory . '/' . $fileName . '.blade.php';
    }

    /**
     * Получает полный путь к файлу шаблона.
     *
     * @param string $name Имя шаблона в dot notation
     * @return string Полный путь к файлу
     */
    private function getTemplateFilePath(string $name): string
    {
        $relativePath = $this->templateNameToPath($name);
        return resource_path('views/' . $relativePath);
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

