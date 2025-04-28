<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningTask;
use App\Models\LearningTaskExercise;
use App\Models\LearningWeek;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExerciseController extends Controller
{

  public function index(Request $request, $taskId)
  {
    $task = LearningTask::where('id', $taskId)->where('user_id', Auth::id())->first();

    if (!$task) {
      return response()->json([
        'message' => 'Không tìm thấy công việc này, vui kiểm tra lại.',
        'status'  => 400,
      ], 400);
    }

    $exercises = LearningTaskExercise::where('learning_task_id', $task->id)->get();

    $exercises->each(function (LearningTaskExercise $exercise) {
      if (!$exercise->start_time) {
        $exercise->update(['start_time' => now()]);
      };
    });

    return response()->json([
      'data'    => $exercises,
      'status'  => 200,
      'message' => 'Lấy danh sách bài tập thành công.',
    ]);
  }

  public function indexByWeek(Request $request, $weekId)
  {
    $week = LearningWeek::where('id', $weekId)->where('user_id', Auth::id())->first();

    if (!$week) {
      return response()->json([
        'message' => 'Không tìm thấy tuần học này, vui kiểm tra lại.',
        'status'  => 400,
      ], 400);
    }

    $exercises = LearningTaskExercise::whereIn('learning_task_id', $week->tasks->pluck('id'))->get();

    return response()->json([
      'data'    => $exercises,
      'status'  => 200,
      'message' => 'Lấy danh sách bài tập thành công.',
    ]);
  }

  public function submit(Request $request)
  {
    $payload = $request->validate([
      'id'          => 'required|integer|exists:learning_task_exercises,id',
      'user_answer' => 'required|string',
    ]);

    $exercise = LearningTaskExercise::where('id', $payload['id'])->where('user_id', Auth::id())->first();

    if (!$exercise) {
      return response()->json([
        'message' => 'Không tìm thấy bài tập này, vui lòng kiểm tra lại.',
        'status'  => 400,
      ], 400);
    }

    $task = LearningTask::where('id', $exercise->learning_task_id)->where('user_id', Auth::id())->first();

    if (!$task) {
      return response()->json([
        'message' => 'Không tìm thấy công việc này, vui kiểm tra lại.',
        'status'  => 400,
      ], 400);
    }

    $profile = LearningWeek::where('id', $task->learning_week_id)->first();

    if (!$profile) {
      return response()->json([
        'message' => 'Không tìm thấy hồ sơ này, vui lòng kiểm tra lại.',
        'status'  => 400,
      ], 400);
    }

    if (!$profile->is_active) {
      return response()->json([
        'status'  => 400,
        'message' => 'Hồ sơ này đã bị khóa, vui lòng kiểm tra lại.',
      ], 400);
    }

    if ($exercise->is_submitted) {
      return response()->json([
        'message' => 'Câu hỏi này đã được nộp trước đó, làm câu khác nhé.',
        'status'  => 400,
      ], 400);
    }

    if (!$exercise->start_time) {
      // return response()->json([
      //   'message' => 'Câu hỏi này chưa được bắt đầu, vui lòng bắt đầu trước khi nộp.',
      //   'status'  => 400,
      // ], 400);
      $exercise->start_time = now();
    }

    $exercise->end_time = now();

    $userAnswer = $request->input('user_answer');

    // Nếu là trắc nghiệm → so sánh trực tiếp
    if ($exercise->type === 'multiple_choice' && 1 === 0) {
      // cho phép chọn nhiều đáp án
      if (!in_array($userAnswer, ['A', 'B', 'C', 'D'])) {
        return response()->json([
          'message' => 'Câu trả lời không hợp lệ, vui lòng chọn A, B, C hoặc D.',
          'status'  => 400,
        ], 400);
      }
      // map
      $userSelected = [
        'A' => 0,
        'B' => 1,
        'C' => 2,
        'D' => 3,
      ][$userAnswer];
      $userAnswer   = $exercise->options[$userSelected] ?? false;

      $isCorrect = strtolower(trim($userAnswer)) === strtolower(trim($exercise->answer));

      if ($isCorrect) {
        $exercise->is_correct = true;
        $exercise->user_score = $exercise->score;
      } else {
        $exercise->is_correct = false;
        $exercise->user_score = 0;
      }
      $exercise->options[$userSelected];
      $exercise->user_answer = $exercise->options[$userAnswer] ?? null;
    } else {
      if ($exercise->type === 'multiple_choice') {
        if (!in_array($userAnswer, ['A', 'B', 'C', 'D'])) {
          return response()->json([
            'message' => 'Câu trả lời không hợp lệ, vui lòng chọn A, B, C hoặc D.',
            'status'  => 400,
          ], 400);
        }

        $userSelected = [
          'A' => 0,
          'B' => 1,
          'C' => 2,
          'D' => 3,
        ][$userAnswer];
        $userAnswer   = $exercise->options[$userSelected] ?? false;
        // $userAnswer   = str_replace(['A. ', 'B. ', 'C. ', 'D. '], '', $userAnswer);
      }
      // Nếu là tự luận → hỏi AI để đánh giá tương đối
      $content = file_get_contents(storage_path('app/prompts/task-exercise-task.txt'));

      $prompts = str_replace('@exercise', $exercise->exercise, $content);
      $prompts = str_replace('@answer', $exercise->answer, $prompts);
      $prompts = str_replace('@userAnswer', $userAnswer, $prompts);
      $prompts = str_replace('@score', $exercise->score, $prompts);
      $prompts = str_replace('@type', $exercise->type . " - " . json_encode($exercise->options), $prompts);
      // $prompts = "Question: {$exercise->exercise}\nAnswer: {$exercise->answer}\nUser's Answer: {$userAnswer}\n\nIs the user's answer correct? Answer yes or no and explain why. Give a score from 0 to {{ $exercise->score }} based on completeness and correctness.";


      $aiResponse = Http::timeout(180)->withToken(config('services.deepseek.key'))
        ->post(config('services.deepseek.url'), [
          'top_p'       => 1,
          'model'       => config('services.deepseek.model'),
          'temperature' => (double) config('services.deepseek.temperature'),
          'messages'    => [
            ['role' => 'user', 'content' => $prompts],
            [
              'role'    => 'user',
              'content' => "IMPORTANT: Using language: " . ($profile->language ?? 'Vietnamese') . " for this content."
            ],
          ],
        ]);


      if (!$aiResponse->successful()) {
        return response()->json([
          'data'    => [
            'error' => $aiResponse->json('error.message'),
          ],
          'status'  => 400,
          'message' => 'Ôi không, có lỗi xảy ra trong quá trình xử lý yêu cầu của bạn. Vui lòng thử lại sau.',
        ], 422);
      }

      $data = $aiResponse->json('choices.0.message.content');

      $data = str_replace('```json', '', $data);
      $data = str_replace('```', '', $data);

      $parsed = json_decode($data, true);

      if (!$parsed || !isset($parsed['is_correct'])) {
        return response()->json([
          'data'    => null,
          'status'  => 400,
          'message' => 'Ôi không, có lỗi xảy ra trong quá trình xử lý yêu cầu của bạn. Vui lòng thử lại sau.',
        ], 400);
      }

      $ai_answer      = $parsed['ai_answer'] ?? '';
      $is_correct     = $parsed['is_correct'] ?? false;
      $user_score     = $parsed['user_score'] ?? 0;
      $ai_feedback    = $parsed['ai_feedback'] ?? '';
      $ai_evaluation  = $parsed['ai_evaluation'] ?? '';
      $ai_explanation = $parsed['ai_explanation'] ?? '';

      $exercise->ai_answer   = $ai_answer;
      $exercise->is_correct  = $is_correct;
      $exercise->user_score  = $user_score;
      $exercise->user_answer = $userAnswer;

      $exercise->ai_feedback    = $ai_feedback;
      $exercise->ai_evaluation  = $ai_evaluation;
      $exercise->ai_explanation = $ai_explanation;
    }

    $exercise->is_submitted = true;

    $exercise->save();

    return response()->json([
      'data'    => [
        'date'           => [
          'start_time' => $exercise->start_time,
          'end_time'   => $exercise->end_time,
          'duration'   => $exercise->duration,
        ],
        'score'          => $exercise->user_score,
        'answer'         => $exercise->answer,
        'correct'        => $exercise->is_correct,
        'your_answer'    => $userAnswer,
        'ai_feedback'    => $exercise->ai_feedback,
        'ai_explanation' => $exercise->ai_explanation,
      ],
      'status'  => 200,
      'message' => 'Đã nộp bài tập thành công.',
    ]);
  }

  public function summary(Request $request)
  {
    $payload = $request->validate([
      'week_id' => 'required|integer|exists:learning_weeks,id',
    ]);

    $lastWeek = LearningWeek::with(['tasks.exercises'])->find($payload['week_id']);

    if (!$lastWeek) {
      return response()->json([
        'message' => 'Không tìm thấy tuần học này, vui lòng kiểm tra lại.',
        'status'  => 400,
      ], 400);
    }

    $profile = $lastWeek->profile;

    if (!$profile) {
      return response()->json([
        'message' => 'Không tìm thấy hồ sơ này, vui lòng kiểm tra lại.',
        'status'  => 400,
      ], 400);
    }

    if ($lastWeek->tasks()->where('is_done', false)->exists()) {
      return response()->json(['status' => 400, 'message' => 'Bạn cần hoàn thành tất cả bài tập được giao trước khi phân tích.'], 400);
    }

    $submittedExercises = $lastWeek->tasks()
      ->with('exercises')
      ->get()
      ->flatMap(fn($task) => $task->exercises->where('is_submitted', true))
      ->values();

    if ($submittedExercises->isEmpty()) {
      return response()->json([
        'message' => 'Không tìm thấy bài tập nào đã nộp, vui lòng kiểm tra lại.',
        'status'  => 400,
      ], 400);
    }

    $analyzeData = [
      'exercises'       => $submittedExercises->map(fn($e) => [
        'exercise'    => $e->exercise,
        'user_answer' => $e->user_answer,
        'is_correct'  => $e->is_correct,
        'user_score'  => $e->user_score,
        'ai_feedback' => $e->ai_feedback,
        'difficulty'  => $e->difficulty,
        'score'       => $e->score,
        'type'        => $e->type,
        'task_focus'  => $e->task->focus ?? '',
        'task_title'  => $e->task->task ?? '',
      ])->toArray(),
      'completed_tasks' => $lastWeek->tasks->where('is_done', true)->map(fn($t) => [
        'title'  => $t->task,
        'focus'  => $t->focus,
        'type'   => $t->type,
        'theory' => $t->theory,
      ])->toArray(),
      'skill_level'     => $profile->skill_level,
      'primary_skill'   => $profile->primary_skill,
      'language'        => $profile->language ?? 'English',
      'week_summary'    => $lastWeek->summary ?? '',
      'learning_goals'  => $profile->goals ?? '',
      'learning_style'  => $profile->learning_style ?? '',
    ];

    $feedback = $lastWeek->feedback;
    if (!$feedback) {
      $summaryPrompt = file_get_contents(storage_path('app/prompts/task-exercise-summary.txt')) . "\n\n" . json_encode($analyzeData);

      $summaryResponse = Http::timeout(180)->withToken(config('services.deepseek.key'))->post(config('services.deepseek.url'), [
        'model'    => config('services.deepseek.model'),
        'messages' => [
          ['role' => 'system', 'content' => $summaryPrompt],
          ['role' => 'user', 'content' => 'Use ' . ($profile->language ?? 'English')],
        ],
      ]);

      if (!$summaryResponse->successful()) {
        Log::error('Failed to summarize exercises.', ['profile_id' => $profile->id]);
        return response()->json(['status' => 400, 'message' => 'Không thể phân tích bài tập.'], 400);
      }

      $summaryContent = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $summaryResponse->json('choices.0.message.content'));
      $feedback       = json_decode($summaryContent, true) ?? ['analysis' => $summaryContent];

      $lastWeek->update(['feedback' => $feedback]);
    }

    return response()->json([
      'data'    => [
        'feedback' => $feedback,
        'summary'  => $analyzeData,
      ],
      'status'  => 200,
      'message' => 'Đã tiến hành phân tích dữ liệu bài tập thành công.',
    ]);
  }

}
