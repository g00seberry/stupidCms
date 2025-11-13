<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware для добавления заголовков Vary: Origin, Cookie к ответам с cookies.
 *
 * Обеспечивает корректное поведение кэша при наличии cookies,
 * так как ответы с cookies могут различаться в зависимости от заголовков Origin и Cookie.
 *
 * @package App\Http\Middleware
 */
final class AddCacheVary
{
    /**
     * Обработать входящий запрос.
     *
     * Добавляет заголовки Vary: Origin, Cookie к ответам, которые устанавливают cookies.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Closure $next Следующий middleware
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Add Vary headers for responses that set cookies
        if ($response->headers->has('Set-Cookie')) {
            $existingVary = $response->headers->get('Vary', '');
            $varyHeaders = array_filter(explode(',', $existingVary));
            $varyHeaders = array_map('trim', $varyHeaders);

            // Add Origin and Cookie if not already present
            if (!in_array('Origin', $varyHeaders, true)) {
                $varyHeaders[] = 'Origin';
            }
            if (!in_array('Cookie', $varyHeaders, true)) {
                $varyHeaders[] = 'Cookie';
            }

            $response->header('Vary', implode(', ', $varyHeaders));
        }

        return $response;
    }
}

