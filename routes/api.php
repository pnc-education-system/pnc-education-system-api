<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\Student\StudentController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\Role\RoleController;
use App\Http\Controllers\Api\V1\SelectionBatch\SelectionBatchController;
use App\Http\Controllers\DashboardController;
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
        Route::get('dashboard/enrollment', [DashboardController::class, 'enrollmentStats']);
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

        Route::middleware('permission:students.import')->group(function () {
            Route::post('imports',              [ImportController::class, 'upload']);
            Route::get('imports',               [ImportController::class, 'index']);
            Route::get('imports/{import}',      [ImportController::class, 'show']);
            Route::post('imports/{import}/commit', [ImportController::class, 'commit']);
            Route::get('imports/{import}/errors',  [ImportController::class, 'errors']);
        });

        Route::middleware('permission:students.view')->group(function () {
            Route::get('students', [StudentController::class, 'index']);
        });
        Route::get('students/{id}', [StudentController::class, 'show'])
            ->whereNumber('id')
            ->middleware('permission:students.view,students.edit,enrollment.manage');
        Route::middleware('permission:students.edit')->group(function () {
            Route::post('students', [StudentController::class, 'store']);
            Route::post('students/bulk-status', [StudentController::class, 'bulkUpdateStatus']);
            Route::post('students/bulk-confirm', [StudentController::class, 'bulkConfirm']);
            Route::put('students/{id}', [StudentController::class, 'update'])->whereNumber('id');
            Route::post('students/{id}', [StudentController::class, 'update'])->whereNumber('id');
            Route::post('students/{id}/photo', [StudentController::class, 'uploadPhoto'])->whereNumber('id');
        });
        Route::middleware('permission:enrollment.manage')->group(function () {
            Route::patch('students/{id}/status', [StudentController::class, 'updateStatus']);
        });
        Route::get('selection-batches', [SelectionBatchController::class, 'index']);
        Route::get('selection-batches/{id}', [SelectionBatchController::class, 'show']);

        Route::middleware('permission:batches.manage')->group(function () {
            Route::post('selection-batches', [SelectionBatchController::class, 'store']);
            Route::put('selection-batches/{id}', [SelectionBatchController::class, 'update']);
            Route::delete('selection-batches/{id}', [SelectionBatchController::class, 'destroy']);
        });
    });
});
