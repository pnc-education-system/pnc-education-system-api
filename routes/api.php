<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:api');
