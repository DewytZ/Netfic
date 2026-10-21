<?php

use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\TitleController;
use Illuminate\Support\Facades\Route;

// Rutas públicas de la API para la App Móvil Android
Route::post('/login', [ApiAuthController::class, 'login']);
Route::post('/register', [ApiAuthController::class, 'register']);
Route::get('/titles', [TitleController::class, 'index']);

// Rutas protegidas con Sanctum (Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::get('/user', [ApiAuthController::class, 'user']);
});
