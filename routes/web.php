<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\TitleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:auth')->name('password.email');
});

// Sin "guest": el enlace del correo debe abrir aunque haya una sesión iniciada en el navegador.
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Panel de administración y API de títulos: solo administradores (rol "admin" en la tabla users).
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin', fn () => view('admin.index'))->name('admin.index');

    // Las escrituras también validan el token CSRF (el panel lo envía en la cabecera X-CSRF-TOKEN).
    Route::prefix('api')->group(function () {
        Route::get('/titles', [TitleController::class, 'index']);
        Route::post('/titles', [TitleController::class, 'store']);
        Route::put('/titles/{title}', [TitleController::class, 'update']);
        Route::patch('/titles/{title}', [TitleController::class, 'update']);
        Route::delete('/titles/{title}', [TitleController::class, 'destroy']);
    });
});
