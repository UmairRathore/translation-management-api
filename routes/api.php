<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [AuthController::class, 'token']);

Route::get('/translations/export', [TranslationController::class, 'export']);
Route::get('/translations', [TranslationController::class, 'index']);
Route::get('/translations/{key}', [TranslationController::class, 'show'])
    ->where('key', '[A-Za-z0-9._-]+');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/translations', [TranslationController::class, 'store']);
    Route::put('/translations/{key}', [TranslationController::class, 'update'])
        ->where('key', '[A-Za-z0-9._-]+');
});
