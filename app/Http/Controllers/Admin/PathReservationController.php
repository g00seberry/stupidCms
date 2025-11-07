<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Routing\PathReservationService;
use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use App\Domain\Routing\Exceptions\InvalidPathException;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyPathReservationRequest;
use App\Http\Requests\StorePathReservationRequest;
use App\Models\Audit;
use App\Models\ReservedRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PathReservationController extends Controller
{
    public function __construct(
        private PathReservationService $service
    ) {}

    /**
     * POST /api/v1/admin/reservations
     */
    public function store(StorePathReservationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
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

            return response()->json([
                'message' => 'Path reserved successfully',
            ], Response::HTTP_CREATED);
        } catch (InvalidPathException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (PathAlreadyReservedException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_CONFLICT,
                [
                    'path' => $e->path,
                    'owner' => $e->owner,
                ]
            );
        }
    }

    /**
     * DELETE /api/v1/admin/reservations/{path}
     * 
     * Поддерживает path как в URL параметре, так и в JSON body (для экзотических URL-encode кейсов).
     */
    public function destroy(string $path, DestroyPathReservationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Если path не в URL (пустой или невалидный), берём из body
        $actualPath = $request->getPath();
        if (empty($actualPath)) {
            return $this->errorResponse(
                'Path is required either in URL parameter or request body.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $this->service->releasePath($actualPath, $validated['source']);

            // Аудит: логируем освобождение
            $this->logAudit('release', $actualPath, [
                'source' => $validated['source'],
            ]);

            return response()->json([
                'message' => 'Path released successfully',
            ], Response::HTTP_OK);
        } catch (ForbiddenReservationRelease $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_FORBIDDEN,
                [
                    'path' => $e->path,
                    'owner' => $e->owner,
                    'attempted_source' => $e->attemptedSource,
                ]
            );
        }
    }

    /**
     * GET /api/v1/admin/reservations
     */
    public function index(): JsonResponse
    {
        $reservations = ReservedRoute::orderBy('path')->get();

        return response()->json([
            'data' => $reservations->map(fn($r) => [
                'path' => $r->path,
                'kind' => $r->kind,
                'source' => $r->source,
                'created_at' => $r->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Форматирует ошибку в формате RFC 7807
     */
    private function errorResponse(
        string $detail,
        int $status,
        array $extensions = []
    ): JsonResponse {
        $response = [
            'type' => 'about:blank',
            'title' => match($status) {
                409 => 'Conflict',
                422 => 'Unprocessable Entity',
                403 => 'Forbidden',
                default => 'Error',
            },
            'status' => $status,
            'detail' => $detail,
        ];

        if (!empty($extensions)) {
            $response = array_merge($response, $extensions);
        }

        return response()->json($response, $status);
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
