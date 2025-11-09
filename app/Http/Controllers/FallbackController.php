<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FallbackRequest;
use App\Http\Resources\Errors\FallbackProblemResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

final class FallbackController extends Controller
{
    public function __invoke(FallbackRequest $request): Response|View|FallbackProblemResource
    {
        Log::info('404 Not Found', [
            'path' => $request->path(),
            'method' => $request->method(),
            'referer' => $request->header('referer'),
            'accept' => $request->header('accept'),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return new FallbackProblemResource($request->path());
        }

        return response()->view('errors.404', [
            'path' => $request->path(),
        ], Response::HTTP_NOT_FOUND);
    }
}