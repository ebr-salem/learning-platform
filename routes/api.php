<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReleaseController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/app/releases/latest', [ReleaseController::class, 'latest']);
    Route::get('/app/releases/latest/download', [ReleaseController::class, 'download']);

    Route::post('/app/releases', [ReleaseController::class, 'publish'])
        ->middleware(['auth:sanctum', 'role:admin']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('assistant')
            ->middleware('role:assistant')
            ->group(function () {
                Route::get('/student/scan/{qr_code_string}', [AssistantController::class, 'scanStudent']);
                Route::post('/attendance/register', [AssistantController::class, 'registerAttendance']);
            });

        Route::prefix('student')
            ->middleware('role:student')
            ->group(function () {
                Route::get('/profile', [StudentController::class, 'profile']);
                Route::get('/lessons', [StudentController::class, 'lessons']);
                Route::get('/lessons/{id}', [StudentController::class, 'lesson']);
            });
    });
});
