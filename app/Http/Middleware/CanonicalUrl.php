<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для канонизации URL публичных страниц.
 * 
 * Выполняет 301 редиректы для:
 * - Приведения к нижнему регистру: /About → /about
 * - Удаления завершающего слэша: /about/ → /about
 * 
 * Применяется только к публичным контентным маршрутам (web_content.php),
 * не затрагивает админку (/admin/*) и API (/api/*).
 */
class CanonicalUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Получаем оригинальный путь из REQUEST_URI (с trailing slash, если есть)
        $originalUri = $request->server('REQUEST_URI', '');
        $pathInfo = parse_url($originalUri, PHP_URL_PATH) ?: '';
        $path = trim($pathInfo, '/');
        
        // Пропускаем системные пути (админка, API) - они не должны канонизироваться
        if ($this->isSystemPath($path)) {
            return $next($request);
        }
        
        // Нормализуем путь: приводим к нижнему регистру и удаляем завершающий слэш
        $normalized = strtolower($path);
        $normalized = rtrim($normalized, '/');
        
        // Проверяем также оригинальный путь на trailing slash
        $hasTrailingSlash = $pathInfo !== '/' && substr($pathInfo, -1) === '/';
        $needsRedirect = $path !== $normalized || $hasTrailingSlash;
        
        // Если путь изменился (регистр или trailing slash), делаем 301 редирект
        if ($needsRedirect) {
            $canonical = '/' . $normalized;
            
            // Сохраняем query string, если есть
            if ($request->getQueryString()) {
                $canonical .= '?' . $request->getQueryString();
            }
            
            return redirect($canonical, 301);
        }
        
        return $next($request);
    }

    /**
     * Проверяет, является ли путь системным (админка, API и т.д.).
     * 
     * @param string $path Путь без ведущего '/'
     * @return bool
     */
    private function isSystemPath(string $path): bool
    {
        // Системные префиксы, которые не должны канонизироваться
        $systemPrefixes = ['admin', 'api', 'auth', 'login', 'logout', 'register'];
        
        $firstSegment = strtolower(explode('/', $path)[0]);
        
        return in_array($firstSegment, $systemPrefixes, true);
    }
}

