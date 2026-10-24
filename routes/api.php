<?php

use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\TitleController;
use Illuminate\Support\Facades\Route;

// Rutas públicas de la API para la App Móvil Android
Route::post('/login', [ApiAuthController::class, 'login']);
Route::post('/register', [ApiAuthController::class, 'register']);

// Catálogo de películas y series de la base de datos (tabla titles)
Route::get('/titles', [TitleController::class, 'index']);

// Contenido multimedia libre de copyright (MongoDB): solo lo publicado
Route::get('/contents', [ContentController::class, 'index']);
Route::get('/contents/genres', [ContentController::class, 'genres']);
Route::get('/contents/{content}', [ContentController::class, 'show']);

// Rutas protegidas con Sanctum (Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::get('/user', [ApiAuthController::class, 'user']);

    // Gestión del contenido multimedia: solo administradores
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/contents', [ContentController::class, 'adminIndex']);
        Route::post('/contents', [ContentController::class, 'store']);
        Route::match(['put', 'patch'], '/contents/{content}', [ContentController::class, 'update']);
        Route::delete('/contents/{content}', [ContentController::class, 'destroy']);
    });
});
