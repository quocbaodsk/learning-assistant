<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {
    Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);

    // Routes yêu cầu authentication
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);
        Route::get('/user', [App\Http\Controllers\Api\AuthController::class, 'user']);
    });
});

Route::middleware('auth:sanctum')->prefix('learning')->group(function () {
    // Profile
    Route::get('/profiles', [App\Http\Controllers\Api\LearningPlanController::class, 'getProfiles']);
    Route::post('/profiles', [App\Http\Controllers\Api\LearningPlanController::class, 'createProfile']);
    Route::delete('/profiles/{profileId}', [App\Http\Controllers\Api\LearningPlanController::class, 'deleteProfile']);

    // Generate tuần mới
    Route::post('/generate/{profileId}', [App\Http\Controllers\Api\LearningPlanController::class, 'generateWeek']);
    Route::post('/generate-next/{profileId}', [App\Http\Controllers\Api\LearningPlanController::class, 'generateNextWeekFromPrevious']);

    // Check
    Route::get('/check-ready/{profileId}', [App\Http\Controllers\Api\LearningPlanController::class, 'checkReadyToGenerate']);

    // Task
    Route::get('/tasks/{profileId}', [App\Http\Controllers\Api\LearningPlanController::class, 'getGroupedTasksOfActiveWeek']);
    Route::get('/tasks/of-week/{weekId}', [App\Http\Controllers\Api\LearningPlanController::class, 'getGroupedTasksOfWeek']);

    Route::get('/tasks/grouped/{profileId}', [App\Http\Controllers\Api\LearningPlanController::class, 'getGroupedTasksOfActiveWeek']);
    Route::get('/tasks/grouped/of-week/{weekId}', [App\Http\Controllers\Api\LearningPlanController::class, 'getGroupedTasksOfWeek']);


    Route::patch('/task/{taskId}', [App\Http\Controllers\Api\LearningPlanController::class, 'updateTaskStatus']);
    Route::patch('/task/update/{taskId}', [App\Http\Controllers\Api\LearningPlanController::class, 'updateTaskContent']);

    // Stats & Export
    Route::get('/weeks/history/{profileId}', [App\Http\Controllers\Api\LearningPlanController::class, 'getWeekHistory']);
    Route::get('/weeks/{weekId}/export', [App\Http\Controllers\Api\LearningPlanController::class, 'exportWeekPdf']);
    Route::get('/weeks/{weekId}/email', [App\Http\Controllers\Api\LearningPlanController::class, 'emailWeekPdf']);
    Route::get('/prompt/suggest/{profileId}', [App\Http\Controllers\Api\LearningPlanController::class, 'suggestNextWeekPrompt']);
});
