<?php

use App\Http\Controllers\AdminPingController;
use App\Models\Entry;
use Illuminate\Support\Facades\Route;

// Главная страница (должна быть в core, чтобы не перехватывалась контентным catch-all)
Route::get('/', \App\Http\Controllers\HomeController::class)->name('home');

// Тестовый маршрут для проверки порядка роутинга (только для тестов)
// Должен обрабатываться до fallback
if (app()->environment('testing')) {
    Route::get('/admin/ping', [AdminPingController::class, 'ping']);
}

// Тестовый маршрут для проверки авторизации (только для тестов)
if (app()->environment('testing')) {
    Route::get('/test/admin/entries', fn() => response()->json(['message' => 'ok']))
        ->middleware('can:viewAny,' . Entry::class);
}

// Статические сервисные пути (примеры - можно расширить)
// Route::get('/health', fn() => response()->json(['status' => 'ok']));
// Route::get('/feed.xml', [FeedController::class, 'index']);
// Route::get('/sitemap.xml', [SitemapController::class, 'index']);

