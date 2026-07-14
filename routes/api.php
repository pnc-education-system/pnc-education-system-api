<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\ImportController;
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('password/reset', [AuthController::class, 'requestReset'])->middleware('throttle:3,1');
        Route::post('password/reset/confirm', [AuthController::class, 'confirmReset'])->middleware('throttle:3,1');
    });
    Route::middleware('jwt.auth')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::middleware('permission:users.manage')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::patch('users/{user}/toggle', [UserController::class, 'toggle']);
        });
        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index']);
            Route::post('roles', [RoleController::class, 'store']);
            Route::get('roles/{id}', [RoleController::class, 'show']);
            Route::put('roles/{id}', [RoleController::class, 'update']);
            Route::delete('roles/{id}', [RoleController::class, 'destroy']);
            Route::get('permissions', [RoleController::class, 'permissions']);
        });

        Route::middleware('permission:enrollment.manage')->group(function () {
            Route::get('imports', [ImportController::class, 'index']);
            Route::get('imports/{id}', [ImportController::class, 'show']);
            Route::get('imports/{id}/download-errors', [ImportController::class, 'downloadErrors']);
            Route::post('imports/preview', [ImportController::class, 'preview']);
            Route::post('imports/{id}/commit', [ImportController::class, 'commit']);
        });
    });
});
