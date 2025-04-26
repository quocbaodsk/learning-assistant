<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
  return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->prefix('/account')->group(function () {
  Route::patch('/update-info', [App\Http\Controllers\Api\AccountController::class, 'updateInfo']);
  Route::patch('/update-password', [App\Http\Controllers\Api\AccountController::class, 'updatePassword']);
});

Route::prefix('auth')->group(function () {
  Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
  Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);

  // Routes yêu cầu authentication
  Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/user', [App\Http\Controllers\Api\AuthController::class, 'user']);
  });
});

Route::middleware('auth:sanctum')->prefix('/v1')->group(function () {
  // Profile
  Route::prefix('/profiles')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\LearningPlanController::class, 'getProfiles']);
    Route::post('/store', [App\Http\Controllers\Api\LearningPlanController::class, 'createProfile']);
    Route::delete('/{profileId}', [App\Http\Controllers\Api\LearningPlanController::class, 'deleteProfile']);
  });

  // Generate tuần mới
  Route::post('/generate', [App\Http\Controllers\Api\LearningPlanController::class, 'generateWeek']);
  Route::post('/generate-next', [App\Http\Controllers\Api\LearningPlanController::class, 'generateNextWeekFromPrevious']);

  // Check
  Route::get('/check-ready/{profileId}', [App\Http\Controllers\Api\LearningPlanController::class, 'checkReadyToGenerate']);

  // Task
  Route::prefix('/tasks')->group(function () {
    Route::get('/{profileId}', [App\Http\Controllers\Api\LearningPlanController::class, 'getGroupedTasksOfActiveWeek']);
    Route::get('/of-week/{weekId}', [App\Http\Controllers\Api\LearningPlanController::class, 'getGroupedTasksOfWeek']);

    Route::patch('/{taskId}', [App\Http\Controllers\Api\LearningPlanController::class, 'updateTaskStatus']);
    Route::patch('/update/{taskId}', [App\Http\Controllers\Api\LearningPlanController::class, 'updateTaskContent']);
  });

  // Stats & Export
  Route::prefix('/stats')->group(function () {
    Route::get('/email', [App\Http\Controllers\Api\LearningPlanController::class, 'emailWeekPdf']);
    Route::get('/export', [App\Http\Controllers\Api\LearningPlanController::class, 'exportWeekPdf']);
    Route::get('/history', [App\Http\Controllers\Api\LearningPlanController::class, 'getWeekHistory']);
    Route::post('/prompt/suggest', [App\Http\Controllers\Api\LearningPlanController::class, 'suggestNextWeekPrompt']);
  });

  Route::prefix('/exercises')->group(function () {
    Route::get('/{taskId}', [App\Http\Controllers\Api\ExerciseController::class, 'index'])->where('taskId', '[0-9]+');
    Route::get('/by-week/{weekId}', [App\Http\Controllers\Api\ExerciseController::class, 'indexByWeek'])->where('weekId', '[0-9]+');
    Route::post('/submit', [App\Http\Controllers\Api\ExerciseController::class, 'submit']);
    Route::post('/summary', [App\Http\Controllers\Api\ExerciseController::class, 'summary']);
  });

});
