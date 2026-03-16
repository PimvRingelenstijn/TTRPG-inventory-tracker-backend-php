<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameSystemController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me'])->middleware('auth.supabase');
});

Route::prefix('game-systems')->group(function () {
    Route::post('', [GameSystemController::class, 'store']);
    Route::get('', [GameSystemController::class, 'index']);
    Route::get('{system_id}', [GameSystemController::class, 'show']);
});
