<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use App\Domain\Routing\Exceptions\InvalidPathException;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use App\Domain\Routing\PathReservationService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\DestroyPathReservationRequest;
use App\Http\Requests\StorePathReservationRequest;
use App\Http\Resources\Admin\PathReservationCollection;
use App\Http\Resources\Admin\PathReservationMessageResource;
use App\Models\Audit;
use App\Models\ReservedRoute;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PathReservationController extends Controller
{
    use Problems;

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
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/conflict",
     *   "title": "Conflict",
     *   "status": 409,
     *   "detail": "Path already reserved.",
     *   "path": "/promo",
     *   "owner": "marketing"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Unprocessable Entity",
     *   "status": 422,
     *   "detail": "The path field is required."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function store(StorePathReservationRequest $request): PathReservationMessageResource
    {
        $validated = $request->validated();

        try {
            $this->service->reservePath(
                $validated['path'],
                $validated['source'],
                $validated['reason'] ?? null
            );
        } catch (InvalidPathException $e) {
            throw new HttpResponseException(
                $this->problem(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    'Unprocessable Entity',
                    $e->getMessage()
                )
            );
        } catch (PathAlreadyReservedException $e) {
            throw new HttpResponseException(
                $this->problem(
                    Response::HTTP_CONFLICT,
                    'Conflict',
                    $e->getMessage(),
                    [
                        'path' => $e->path,
                        'owner' => $e->owner,
                    ]
                )
            );
        }

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
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=403 {
     *   "type": "https://stupidcms.dev/problems/forbidden",
     *   "title": "Forbidden",
     *   "status": 403,
     *   "detail": "Only owner marketing may release this path.",
     *   "path": "/promo",
     *   "owner": "marketing",
     *   "attempted_source": "editorial"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation error",
     *   "status": 422,
     *   "detail": "Path is required either in URL parameter or request body."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function destroy(string $path, DestroyPathReservationRequest $request): PathReservationMessageResource
    {
        $validated = $request->validated();

        // Если path не в URL (пустой или невалидный), берём из body
        $actualPath = $request->getPath();
        if (empty($actualPath)) {
            throw new HttpResponseException(
                $this->problem(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    'Validation error',
                    'Path is required either in URL parameter or request body.'
                )
            );
        }

        try {
            $this->service->releasePath($actualPath, $validated['source']);
        } catch (ForbiddenReservationRelease $e) {
            throw new HttpResponseException(
                $this->problem(
                    Response::HTTP_FORBIDDEN,
                    'Forbidden',
                    $e->getMessage(),
                    [
                        'path' => $e->path,
                        'owner' => $e->owner,
                        'attempted_source' => $e->attemptedSource,
                    ]
                )
            );
        }

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
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
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
