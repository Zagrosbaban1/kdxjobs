<?php

use App\Http\Controllers\LegacyController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post', 'options'], '/api/{path?}', [LegacyController::class, 'api'])
    ->where('path', '.*')
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

Route::get('/assets/{path}', [LegacyController::class, 'asset'])
    ->where('path', '.*');

Route::match(['get', 'post'], '/', [LegacyController::class, 'handle'])
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

Route::match(['get', 'post'], '/index.php', [LegacyController::class, 'handle'])
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);
