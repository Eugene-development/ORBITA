<?php
use App\Modules\Auth\AuthController;
use App\Modules\Media\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/csrf', [AuthController::class, 'csrf'])->middleware('throttle:60,1');
Route::get('/auth/me', [AuthController::class, 'me'])->middleware(['auth.session', 'throttle:120,1']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:employee-login');
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('throttle:60,1');
Route::post('/auth/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/auth/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::post('/api/media', [MediaController::class, 'upload'])->middleware(['auth', 'auth.session', 'throttle:20,1']);
