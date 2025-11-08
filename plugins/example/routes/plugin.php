<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('example')->group(function (): void {
    Route::get('ping', static fn () => response()->json(['ok' => true]));
});

