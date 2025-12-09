<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SlugifyPreviewResource;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\HttpErrorException;
use App\Support\Slug\Slugifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

/**
 * Контроллер для утилитарных операций в админ-панели.
 *
 * Предоставляет вспомогательные операции: генерация slug'ов,
 * предпросмотр результатов.
 *
 * @package App\Http\Controllers\Admin\V1
 */
class UtilsController extends Controller
{
    /**
     * @param \App\Support\Slug\Slugifier $slugifier Генератор slug'ов
     */
    public function __construct(
        private Slugifier $slugifier,
    ) {}

    /**
     * Генерация slug предпросмотра.
     *
     * @group Admin ▸ Utils
     * @name Slugify preview
     * @authenticated
     * @queryParam title string required Заголовок (<=500). Example: New landing page
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

        // Генерируем slug из заголовка
        // Примечание: для обратной совместимости возвращаем base и unique
        // (unique всегда равен base, проверка уникальности будет в будущем для route_nodes)
        $base = $this->slugifier->slugify($title);

        if (empty($base)) {
            return new SlugifyPreviewResource('', '');
        }

        return new SlugifyPreviewResource($base, $base);
    }
}

