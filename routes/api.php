<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;

/*
|--------------------------------------------------------------------------
| API V1 Routes — PNC Education System
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public ────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });

    // ── Protected ─────────────────────────────────────────────────────────
    Route::middleware('auth:api')->group(function () {

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // ── Admin: Users & Roles ────────────────────────────────────────
        Route::middleware('permission:users.manage')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::patch('users/{user}/toggle', [UserController::class, 'toggle']);
        });

        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index']);
            Route::put('roles/{role}', [RoleController::class, 'update']);
            Route::get('permissions', [RoleController::class, 'permissions']);
        });
    });
});
