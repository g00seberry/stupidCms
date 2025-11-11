<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\Routing\PathReservationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyPathReservationRequest;
use App\Http\Requests\StorePathReservationRequest;
use App\Http\Resources\Admin\PathReservationCollection;
use App\Http\Resources\Admin\PathReservationMessageResource;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use App\Models\Audit;
use App\Models\ReservedRoute;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PathReservationController extends Controller
{
    use ThrowsErrors;

    public function __construct(
        private readonly PathReservationService $service
    ) {}

    /**
     * Создание резервирования пути.
     *
     * @group Admin ▸ Path reservations
     * @name Reserve path
     * @authenticated
     * @bodyParam path string required Путь или префикс (<=255). Example: blog/*
     * @bodyParam source string required Идентификатор источника (<=100). Example: marketing
     * @bodyParam reason string Причина (<=255). Example: Landing redesign freeze
     * @response status=201 {
     *   "message": "Path reserved successfully"
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "31111111-2222-3333-4444-555555555555",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-31111111222233334444555555555555-3111111122223333-01"
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/conflict",
     *   "title": "Path already reserved",
     *   "status": 409,
     *   "code": "CONFLICT",
     *   "detail": "Path already reserved.",
     *   "meta": {
     *     "request_id": "31111111-2222-3333-4444-555555555556",
     *     "path": "/promo",
     *     "owner": "marketing"
     *   },
     *   "trace_id": "00-31111111222233334444555555555556-3111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The path field is required.",
     *   "meta": {
     *     "request_id": "31111111-2222-3333-4444-555555555557",
     *     "errors": {
     *       "path": [
     *         "The path field is required."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-31111111222233334444555555555557-3111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "36666666-7777-8888-9999-000000000000",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-36666666777788889999000000000000-3666666677778888-01"
     * }
     */
    public function store(StorePathReservationRequest $request): PathReservationMessageResource
    {
        $validated = $request->validated();

        $this->service->reservePath(
            $validated['path'],
            $validated['source'],
            $validated['reason'] ?? null
        );

        // Аудит: логируем резервирование
        $this->logAudit('reserve', $validated['path'], [
            'source' => $validated['source'],
            'reason' => $validated['reason'] ?? null,
        ]);

        return new PathReservationMessageResource('Path reserved successfully', Response::HTTP_CREATED);
    }

    /**
     * Удаление резервирования пути.
     *
     * Поддерживает path как в URL параметре, так и в теле запроса.
     *
     * @group Admin ▸ Path reservations
     * @name Release path
     * @authenticated
     * @urlParam path string required URL-кодированный путь. Example: blog%2F*
     * @bodyParam source string required Текущий владелец резервирования. Example: marketing
     * @bodyParam path string Альтернативный путь (если сложный URL). Example: blog/*
     * @response status=200 {
     *   "message": "Path released successfully"
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "31111111-2222-3333-4444-555555555558",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-31111111222233334444555555555558-3111111122223333-01"
     * }
     * @response status=403 {
     *   "type": "https://stupidcms.dev/problems/forbidden",
     *   "title": "Forbidden",
     *   "status": 403,
     *   "code": "FORBIDDEN",
     *   "detail": "Only owner marketing may release this path.",
     *   "meta": {
     *     "request_id": "31111111-2222-3333-4444-555555555559",
     *     "path": "/promo",
     *     "owner": "marketing",
     *     "attempted_source": "editorial"
     *   },
     *   "trace_id": "00-31111111222233334444555555555559-3111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "Path is required either in URL parameter or request body.",
     *   "meta": {
     *     "request_id": "31111111-2222-3333-4444-555555555560",
     *     "errors": {
     *       "path": [
     *         "The path field is required either in URL parameter or request body."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-31111111222233334444555555555660-3111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "36666666-7777-8888-9999-000000000001",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-36666666777788889999000000000001-3666666677778888-01"
     * }
     */
    public function destroy(string $path, DestroyPathReservationRequest $request): PathReservationMessageResource
    {
        $validated = $request->validated();

        // Если path не в URL (пустой или невалидный), берём из body
        $actualPath = $request->getPath();
        if (empty($actualPath)) {
            $this->throwError(
                ErrorCode::VALIDATION_ERROR,
                'Path is required either in URL parameter or request body.',
                [
                    'errors' => [
                        'path' => ['The path field is required either in URL parameter or request body.'],
                    ],
                ],
            );
        }

        $this->service->releasePath($actualPath, $validated['source']);

        // Аудит: логируем освобождение
        $this->logAudit('release', $actualPath, [
            'source' => $validated['source'],
        ]);

        return new PathReservationMessageResource('Path released successfully', Response::HTTP_OK);
    }

    /**
     * Список зарезервированных путей.
     *
     * @group Admin ▸ Path reservations
     * @name List reservations
     * @authenticated
     * @response status=200 {
     *   "data": [
     *     {
     *       "path": "/promo",
     *       "kind": "exact",
     *       "source": "marketing",
     *       "created_at": "2025-01-10T12:00:00+00:00"
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
     *     "request_id": "31111111-2222-3333-4444-555555555561",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-31111111222233334444555555555661-3111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "36666666-7777-8888-9999-000000000002",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-36666666777788889999000000000002-3666666677778888-01"
     * }
     */
    public function index(): PathReservationCollection
    {
        $reservations = ReservedRoute::orderBy('path')->get();

        return new PathReservationCollection($reservations);
    }

    /**
     * Логирует действие в таблицу audits.
     */
    private function logAudit(string $action, string $path, array $context = []): void
    {
        try {
            // Находим резервирование для получения ID
            $reservation = ReservedRoute::where('path', $path)->first();

            Audit::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'subject_type' => ReservedRoute::class,
                'subject_id' => $reservation?->id ?? 0, // 0 если не найдено (для release несуществующего)
                'diff_json' => [
                    'path' => $path,
                    ...$context,
                ],
                'ip' => request()->ip(),
                'ua' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Не падаем, если аудит не записался
            Log::warning('Failed to log path reservation audit', [
                'action' => $action,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
