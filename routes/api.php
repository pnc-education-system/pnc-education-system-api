<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\Student\StudentController;
use App\Http\Controllers\Api\V1\Student\StudentCardController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\Role\RoleController;
use App\Http\Controllers\Api\V1\Record\RecordAttachmentController;
use App\Http\Controllers\Api\V1\SelectionBatch\SelectionBatchController;
use App\Http\Controllers\Api\V1\CardsController;
use App\Http\Controllers\Api\V1\Student\StudentRecordController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\DashboardController;
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        // Refresh must be public — the JWT is already expired when we need to refresh
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('password/reset', [AuthController::class, 'requestReset'])->middleware('throttle:3,1');
        Route::post('password/reset/confirm', [AuthController::class, 'confirmReset'])->middleware('throttle:3,1');
    });

    // Public verification routes (no authentication required)
    Route::get('/student-cards/qr/{qr_token}', [StudentCardController::class, 'resolveQr']);
    Route::get('/student-cards/student/{student_id_no}', [StudentCardController::class, 'resolveByStudentId']);
    Route::get('/cards/verify/{qrToken}', [StudentCardController::class, 'verify']);
    Route::get('/students/verify/{studentId}', [StudentCardController::class, 'verifyById'])->whereNumber('studentId');

    // Public photo serving route — no auth required so <img> tags can load photos
    // (photos on student cards are meant to be publicly viewable for verification)
    Route::get('photos/{studentId}', [StudentController::class, 'servePhoto'])->whereNumber('studentId');

    Route::middleware('jwt.auth')->group(function () {
        Route::prefix('reports')->middleware('permission:reports.view')->group(function () {
            Route::get('types', [ReportController::class, 'types']);
            Route::get('filter-sources', [ReportController::class, 'filterSources']);
            Route::get('summary', [ReportController::class, 'summary']);
            Route::post('generate', [ReportController::class, 'generate']);
            Route::get('download-pdf', [ReportController::class, 'downloadPdf']);
        });


        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('dashboard/enrollment', [DashboardController::class, 'enrollmentStats']);

        Route::post('/student-cards', [StudentCardController::class, 'store']);

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
        Route::post('students/{id}/card', [StudentCardController::class, 'generateCard'])
            ->whereNumber('id')
            ->middleware('permission:students.view,students.edit,enrollment.manage');

        Route::get('students/{student}/records', [StudentRecordController::class, 'index'])
            ->whereNumber('student')
            ->middleware('permission:records.view,records.manage');
        Route::get('students/{student}/records/{record}', [StudentRecordController::class, 'show'])
            ->whereNumber('student')
            ->whereNumber('record')
            ->middleware('permission:records.view,records.manage');

        Route::middleware('permission:records.manage')->group(function () {
            Route::post('students/{student}/records', [StudentRecordController::class, 'store'])->whereNumber('student');
            Route::put('students/{student}/records/{record}', [StudentRecordController::class, 'update'])
                ->whereNumber('student')
                ->whereNumber('record');
            Route::delete('students/{student}/records/{record}', [StudentRecordController::class, 'destroy'])
                ->whereNumber('student')
                ->whereNumber('record');
        });

        Route::middleware('permission:enrollment.manage')->group(function () {
            Route::patch('students/{id}/status', [StudentController::class, 'updateStatus']);
        });
        Route::prefix('cards')->group(function () {
            Route::get('templates', [CardsController::class, 'templates']);
            Route::get('templates/{id}', [CardsController::class, 'showTemplate'])->whereNumber('id');
            Route::get('stats', [CardsController::class, 'stats']);
            Route::get('students-by-batch', [CardsController::class, 'studentsByBatch']);
            Route::post('batch', [CardsController::class, 'batch']);
            Route::post('generate/{studentId}', [CardsController::class, 'generate'])->whereNumber('studentId');
            Route::get('download/{studentId}', [CardsController::class, 'download'])->whereNumber('studentId');
            Route::get('download/batch/{batchId}', [CardsController::class, 'downloadByBatch'])->whereNumber('batchId');
            Route::post('batch-download', [CardsController::class, 'batchDownload']);
        });

        Route::middleware('permission:cards.generate')->group(function () {
            Route::post('cards/templates', [CardsController::class, 'storeTemplate']);
            Route::put('cards/templates/{id}', [CardsController::class, 'updateTemplate'])->whereNumber('id');
            Route::delete('cards/templates/{id}', [CardsController::class, 'destroyTemplate'])->whereNumber('id');
        });

        Route::middleware('permission:cards.generate')->group(function () {
            Route::post('cards/reprint', [CardsController::class, 'batchReprint']);
            Route::post('student-cards/{id}/reprint', [CardsController::class, 'reprint'])->whereNumber('id');
        });

        Route::get('selection-batches', [SelectionBatchController::class, 'index']);
        Route::get('selection-batches/{id}', [SelectionBatchController::class, 'show']);


        Route::middleware('permission:batches.manage')->group(function () {
            Route::post('selection-batches', [SelectionBatchController::class, 'store']);
            Route::put('selection-batches/{id}', [SelectionBatchController::class, 'update']);
            Route::delete('selection-batches/{id}', [SelectionBatchController::class, 'destroy']);
        });

        // Listing attachments is a read operation - accessible with records.view
        Route::get('students/{student}/attachments', [RecordAttachmentController::class, 'indexByStudent'])
            ->whereNumber('student')
            ->middleware('permission:records.view,records.manage');

        Route::middleware('permission:records.manage')->group(function () {
            Route::post('records/{record}/attachments', [RecordAttachmentController::class, 'store'])
                ->whereNumber('record');

            // Student-scoped attachment endpoints (for frontend compatibility)
            Route::post('students/{student}/attachments', [RecordAttachmentController::class, 'storeForStudent'])
                ->whereNumber('student');
            Route::delete('students/{student}/attachments/{attachment}', [RecordAttachmentController::class, 'destroy'])
                ->whereNumber('student')
                ->whereNumber('attachment');
        });
    });
});
