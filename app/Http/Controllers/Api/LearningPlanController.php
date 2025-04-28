<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Models\{LearningProfile, LearningWeek, LearningTask, User};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\FacadesLog;
use Illuminate\Support\Facades\Mail;

class LearningPlanController extends Controller
{
  public function getProfiles(Request $request)
  {
    return response()->json([
      'data'    => LearningProfile::where('user_id', Auth::id())->get(),
      'status'  => 200,
      'message' => 'Lấy danh sách profile thành công',
    ]);
  }

  public function createProfile(Request $request)
  {
    $validated = $request->validate([
      'course_name'         => 'required|string|max:255',
      'course_content'      => 'required|array',
      'course_content.*'    => 'string|max:255',
      'primary_skill'       => 'required|string',
      'skill_level'         => 'required|integer|min:0|max:100',
      'secondary_skills'    => 'array',
      'interests'           => 'array',
      'goals'               => 'nullable|string',
      'language'            => 'nullable|string|in:English,Vietnamese,Chinese,Japanese,German',
      'learning_style'      => 'nullable|string',
      'daily_learning_time' => 'nullable|string',
      'preferred_resources' => 'array',
      'custom_ai_prompt'    => 'nullable|string',
    ]);

    $allowedCourses = [
      "Hệ điều hành",
      "Lập trình C++",
      "Cơ sở dữ liệu",
      "Mạng máy tính",
      "Lập trình web",
      "Công nghệ .NET",
      "Lập trình Java",
      "Vật lý đại cương",
      "Cơ sở dữ liệu nâng cao",
    ];

    if (!in_array($validated['course_name'], $allowedCourses)) {
      return response()->json(['status' => 400, 'message' => 'Course name is not allowed.'], 400);
    }

    $validated['user_id'] = Auth::id();

    $profile = LearningProfile::create($validated);

    return response()->json(['data' => $profile, 'status' => 201, 'message' => 'Tạo hồ sơ mới thành công, tiếp tục tạo kế hoạch tuần học mới nhé!'], 201);
  }

  public function deleteProfile(Request $request, $profileId)
  {
    $profile = LearningProfile::where('user_id', Auth::id())->find($profileId);

    if (!$profile) {
      return response()->json(['status' => 400, 'message' => 'Không tìm thấy profile này'], 400);
    }

    $profile->delete(); // cascade tự động xóa week và task nếu đã setup trong migration

    return response()->json([
      'data'    => null,
      'status'  => 200,
      'message' => 'Đã xoá profile và toàn bộ dữ liệu liên quan.',
    ]);
  }

  public function generateWeek(Request $request)
  {
    try {
      // Xác thực dữ liệu đầu vào
      $payload = $request->validate([
        'profile_id' => 'required|integer|exists:learning_profiles,id',
      ]);

      $user    = User::findOrFail(Auth::id());
      $profile = LearningProfile::where('user_id', Auth::id())
        ->find($payload['profile_id']);

      if (!$profile) {
        return response()->json([
          'status'  => 422,
          'message' => 'Không tìm thấy hồ sơ này',
        ], 422);
      }

      // Kiểm tra xem tuần học hiện tại đã tồn tại chưa
      $existing = $profile->weeks()
        ->whereDate('start_date', now()->startOfWeek())
        ->exists();

      if ($existing) {
        return response()->json([
          'status'  => 409,
          'message' => 'Tuần học hiện tại đã tồn tại.',
          'data'    => null,
        ], 409);
      }

      // Kiểm tra trạng thái hoàn thành của tuần học trước
      $lastWeek = $profile->weeks()->where('is_active', true)->latest('start_date')->first();
      if ($lastWeek && $lastWeek->tasks()->where('is_done', false)->exists()) {
        return response()->json([
          'status'  => 400,
          'message' => 'Bạn cần hoàn thành tất cả task tuần trước trước khi tạo tuần mới.',
          'data'    => null,
        ], 400);
      }

      // Cập nhật trạng thái tuần học trước (nếu có)
      if ($lastWeek) {
        $lastWeek->update(['is_active' => false]);
      }

      // Chuẩn bị prompt để gửi đến API
      $content = file_get_contents(storage_path('app/prompts/learning-plan.txt'));

      // Xây dựng user prompt với ngôn ngữ được chỉ định
      $userInfo = [
        "Age"                 => ($profile->user->age ?? '-'),
        "Name"                => ($profile->user->name ?? 'User'),
        "Gender"              => ($profile->user->gender ?? "Other"),
        "Interests"           => implode(', ', $profile->interests ?? []),
        "Occupation"          => ($profile->user->occupation ?? '-'),
        "Course Name"         => ($profile->course_name ?? '-'),
        "Course Content"      => json_encode($profile->course_content ?? []),
        "Primary Skill"       => $profile->primary_skill,
        "Skill Level"         => $profile->skill_level,
        "Secondary Skills"    => implode(', ', $profile->secondary_skills ?? []),
        "Learning Goals"      => $profile->goals,
        "Learning Style"      => $profile->learning_style,
        "Daily Learning Time" => $profile->daily_learning_time,
        "Preferred Resources" => implode(', ', $profile->preferred_resources ?? []),
        "Response Language"   => ($profile->language ?? 'English'),
      ];

      $prompt = "";
      foreach ($userInfo as $key => $value) {
        $prompt .= "$key: $value\n";
      }

      // Gợi ý trả lời
      $prompt .= "IMPORTANT: RESPOND ONLY IN " . strtoupper($profile->language ?? 'English') . " LANGUAGE.";

      // Chèn prompt vào template
      $content = str_replace('{{USER_PROFILE}}', $prompt, $content);

      // Chuẩn bị tin nhắn cho API
      $messages = [
        [
          'role'    => 'system',
          'content' => $content,
        ],
        [
          'role'    => 'user',
          'content' => $prompt,
        ],
      ];

      // Gọi OpenAI API
      $response = Http::timeout(360) // Tăng timeout vì việc tạo nội dung có thể mất thời gian
        ->withToken(config('services.deepseek.key'))
        ->post(config('services.deepseek.url'), [
          'model'       => config('services.deepseek.model'),
          'temperature' => (double) config('services.deepseek.temperature'),
          'top_p'       => 1,
          'messages'    => $messages,
          'user'        => "profile_{$profile->id}_{$profile->user_id}",  // Thêm user ID để theo dõi request
        ]);

      // Kiểm tra phản hồi từ API
      if (!$response->successful()) {
        $errorCode   = $response->status();
        $errorDetail = $response->json() ?? 'Unknown error';

        Log::error('OpenAI API Error', [
          'status'     => $errorCode,
          'response'   => $errorDetail,
          'profile_id' => $profile->id,
        ]);

        return response()->json([
          'data'    => $response->json(),
          'status'  => $errorCode,
          'message' => "Lỗi API ({$errorCode}): Không thể tạo kế hoạch học tập. Vui lòng thử lại sau.",
        ], 422);
      }

      // Trích xuất nội dung từ phản hồi
      // $chatId  = $response->json('');
      $content = $response->json('choices.0.message.content');

      // Làm sạch nội dung JSON từ phản hồi markdown
      $content = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $content);

      // Phân tích JSON
      $parsed = json_decode($content, true);

      // Xác thực dữ liệu JSON
      if (!$parsed || !isset($parsed['weeklyPlan']) || json_last_error() !== JSON_ERROR_NONE) {
        Log::error('JSON Parsing Error', [
          'error'          => json_last_error_msg(),
          'content_sample' => substr($content, 0, 200) . '...',
          'profile_id'     => $profile->id,
        ]);

        return response()->json([
          'status'  => 400,
          'message' => 'Không thể xử lý dữ liệu từ API. Lỗi cấu trúc JSON.',
          'data'    => null,
        ], 400);
      }

      // Sử dụng transaction để đảm bảo tính toàn vẹn dữ liệu
      return DB::transaction(function () use ($profile, $user, $parsed) {
        // Tạo tuần học mới
        $week = $profile->weeks()->create([
          'notes'      => $parsed['notes'] ?? null,
          'summary'    => $parsed['user']['summary'] ?? '',
          'user_id'    => $user->id,
          'is_active'  => true,
          'start_date' => now()->startOfWeek(),
        ]);

        // Tạo các nhiệm vụ và bài tập cho mỗi ngày
        foreach ($parsed['weeklyPlan'] as $day => $tasks) {
          foreach ($tasks as $task) {
            $taskModel = $week->tasks()->create([
              'day'      => $day,
              'task'     => $task['task'],
              'duration' => $task['duration'],
              'resource' => $task['resource'],
              'type'     => $task['type'],
              'focus'    => $task['focus'],
              'theory'   => $task['theory'] ?? null,
              'is_done'  => false,
              'user_id'  => $user->id,
            ]);

            // Tạo các bài tập cho nhiệm vụ
            if (isset($task['exercises']) && is_array($task['exercises'])) {
              foreach ($task['exercises'] as $ex) {
                $taskModel->exercises()->create([
                  'exercise'     => $ex['exercise'],
                  'instructions' => $ex['instructions'] ?? null,
                  'answer'       => $ex['answer'] ?? null,
                  'difficulty'   => $ex['difficulty'] ?? 1,
                  'score'        => $ex['score'] ?? 1,
                  'type'         => $ex['type'] ?? 'written',
                  'options'      => isset($ex['options']) ? ($ex['options']) : null,
                  'user_id'      => $user->id,
                  'user_answer'  => null,
                  'is_submitted' => false,
                ]);
              }
            }
          }
        }

        return response()->json([
          'data'    => $week,
          'status'  => 200,
          'message' => 'Đã tạo tuần học mới thành công, hãy tiếp tục học tập.',
        ]);
      });

    } catch (\Exception $e) {
      Log::error('Generate Week Error: ' . $e->getMessage(), [
        'profile_id' => $request->profile_id ?? null,
        'trace'      => $e->getTraceAsString(),
      ]);

      return response()->json([
        'data'    => null,
        'status'  => 500,
        'message' => 'Có lỗi xảy ra: ' . ($e->getMessage()),
      ], 500);
    }
  }

  public function generateNextWeekFromPrevious(Request $request)
  {
    try {
      $payload = $request->validate([
        'profile_id' => 'required|integer|exists:learning_profiles,id',
      ]);

      $profile = LearningProfile::where('user_id', Auth::id())->findOrFail($payload['profile_id']);

      $lastWeek = $profile->weeks()->latest('start_date')->first();

      if (!$lastWeek) {
        return response()->json(['status' => 400, 'message' => 'Chưa có tuần học trước, vui lòng tạo mới.'], 400);
      }

      if ($lastWeek->is_active || $lastWeek->tasks()->where('is_done', false)->exists()) {
        return response()->json(['status' => 400, 'message' => 'Bạn cần hoàn thành tuần học hiện tại trước.'], 400);
      }

      $lastWeek->update(['is_active' => false]);

      // Lấy bài tập đã nộp
      $submittedExercises = $lastWeek->tasks()
        ->with('exercises')
        ->get()
        ->flatMap(fn($task) => $task->exercises->where('is_submitted', true))
        ->values();

      if ($submittedExercises->isEmpty()) {
        Log::info('No submitted exercises, fallback to basic generation.', ['profile_id' => $profile->id]);
        return $this->generateWeek($request);
      }

      // Build dữ liệu phân tích
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

      // Nếu chưa có feedback, gọi AI phân tích
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

      // Xây prompt để sinh tuần mới
      $userInfo = [
        "Age"                 => $profile->user->age ?? '-',
        "Name"                => $profile->user->name ?? 'User',
        "Gender"              => $profile->user->gender ?? 'Other',
        "Interests"           => implode(', ', $profile->interests ?? []),
        "Occupation"          => $profile->user->occupation ?? '-',
        "Course Name"         => $profile->course_name ?? '-',
        "Primary Skill"       => $profile->primary_skill,
        "Skill Level"         => $profile->skill_level,
        "Secondary Skills"    => implode(', ', $profile->secondary_skills ?? []),
        "Learning Goals"      => $profile->goals,
        "Learning Style"      => $profile->learning_style,
        "Daily Learning Time" => $profile->daily_learning_time,
        "Preferred Resources" => implode(', ', $profile->preferred_resources ?? []),
        "Response Language"   => $profile->language ?? 'English',
        "Last Week's Focus"   => $lastWeek->summary ?? '-'
      ];

      $enhancedPrompt = collect($userInfo)->map(fn($v, $k) => "$k: $v")->implode("\n") . "\n\nIMPORTANT:\n1. RESPOND ONLY IN " . strtoupper($profile->language ?? 'English') . ".\n2. STRICTLY FOLLOW SKILL LEVEL.\n3. BUILD BASED ON LAST WEEK'S PERFORMANCE.\n4. REINFORCE WEAK AREAS.\n\nAnalysis:\n" . json_encode($feedback);

      // Gọi AI sinh tuần mới
      $planResponse = Http::timeout(180)->withToken(config('services.deepseek.key'))->post(config('services.deepseek.url'), [
        'model'    => config('services.deepseek.model'),
        'messages' => [
          ['role' => 'system', 'content' => file_get_contents(storage_path('app/prompts/learning-plan-next.txt'))],
          ['role' => 'user', 'content' => $enhancedPrompt],
        ],
      ]);

      if (!$planResponse->successful()) {
        Log::error('Failed to generate next week.', ['profile_id' => $profile->id]);
        return response()->json(['status' => 400, 'message' => 'Không thể tạo kế hoạch tuần mới.'], 400);
      }

      $planContent = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $planResponse->json('choices.0.message.content'));
      $parsed      = json_decode($planContent, true);

      if (!$parsed || !isset($parsed['weeklyPlan'])) {
        return response()->json(['status' => 400, 'message' => 'Dữ liệu trả về từ AI không hợp lệ.'], 400);
      }

      // Lưu tuần mới
      return DB::transaction(function () use ($profile, $parsed, $feedback) {
        $week = $profile->weeks()->create([
          'user_id'    => Auth::id(),
          'summary'    => $parsed['user']['summary'] ?? '',
          'notes'      => $parsed['notes'] ?? '',
          'start_date' => now()->startOfWeek(),
          'is_active'  => true,
        ]);

        foreach ($parsed['weeklyPlan'] as $day => $tasks) {
          foreach ($tasks as $task) {
            $taskModel = $week->tasks()->create([
              'day'      => $day,
              'task'     => $task['task'],
              'duration' => $task['duration'],
              'resource' => $task['resource'],
              'type'     => $task['type'],
              'focus'    => $task['focus'],
              'theory'   => $task['theory'] ?? null,
              'user_id'  => $week->user_id,
              'is_done'  => false,
            ]);

            foreach ($task['exercises'] ?? [] as $exercise) {
              $taskModel->exercises()->create([
                'exercise'     => $exercise['exercise'],
                'instructions' => $exercise['instructions'] ?? null,
                'answer'       => $exercise['answer'] ?? null,
                'difficulty'   => $exercise['difficulty'] ?? 1,
                'score'        => $exercise['score'] ?? 1,
                'type'         => $exercise['type'] ?? 'written',
                'options'      => $exercise['options'] ?? null,
                'user_id'      => $taskModel->user_id,
                'is_submitted' => false,
              ]);
            }
          }
        }

        return response()->json([
          'data'    => [
            'week'     => $week,
            'feedback' => $feedback,
          ],
          'status'  => 200,
          'message' => 'Tạo tuần học tiếp theo thành công.',
        ]);
      });

    } catch (\Exception $e) {
      Log::error('Generate Next Week Error', [
        'message' => $e->getMessage(),
        'trace'   => $e->getTraceAsString(),
      ]);
      return response()->json(['status' => 500, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()], 500);
    }
  }

  public function updateTaskStatus(Request $request, $taskId)
  {
    $task = LearningTask::whereHas('week', function ($query) use ($request) {
      $query->where('is_active', true)
        ->whereHas('profile', fn($q) => $q->where('user_id', Auth::id()));
    })->find($taskId);

    if (!$task) {
      return response()->json(['status' => 400, 'message' => 'Không tìm thấy task này'], 400);
    }

    if ($task->is_done) {
      return response()->json([
        'status'  => 400,
        'message' => 'Task này đã được đánh dấu hoàn thành rồi.',
      ], 400);
    }

    $isDone = (bool) $request->input('is_done');

    $exercises = $task->exercises()->where('is_submitted', false)->get();

    if ($isDone && $exercises->isNotEmpty()) {
      return response()->json([
        'status'  => 400,
        'message' => 'Bạn cần hoàn thành tất cả bài tập trước khi đánh dấu task này là hoàn thành.',
      ], 400);
    }

    $task->update(['is_done' => $isDone, 'end_time' => now()]);

    // check nếu all task đã xong thì set is_active = 0
    $week = $task->week;

    if ($week) {
      $exists = $week->tasks()->where('is_done', false)->exists();

      if (!$exists) {
        $week->update(['is_active' => false]);
      }
    }

    return response()->json([
      'data'    => $task,
      'status'  => 200,
      'message' => 'Đánh dấu hoàn thành task ' . $task->id . ' thành công.',
    ]);
  }

  public function updateTaskContent(Request $request, $taskId)
  {
    $validated = $request->validate([
      'task'     => 'required|string|max:255',
      'duration' => 'required|string|max:50',
      'resource' => 'required|string|max:255',
      'type'     => 'required|string|max:50',
      'focus'    => 'required|string|max:255',
    ]);

    $task = LearningTask::whereHas('week', function ($q) use ($request) {
      $q->where('is_active', true)
        ->whereHas('profile', fn($q2) => $q2->where('user_id', Auth::id()));
    })->find($taskId);

    if (!$task) {
      return response()->json(['status' => 400, 'message' => 'Không tìm thấy task này'], 400);
    }

    if ($task->is_done) {
      return response()->json([
        'status'  => 400,
        'message' => 'Task này đã được đánh dấu hoàn thành rồi.',
      ], 400);
    }

    $task->update($validated);

    return response()->json([
      'data'    => $task,
      'status'  => 200,
      'message' => 'Cập nhật nội dung task thành công.',
    ]);
  }

  public function checkReadyToGenerate(Request $request, $profileId)
  {
    $profile = LearningProfile::where('user_id', Auth::id())->findOrFail($profileId);
    $week    = $profile->weeks()->latest('start_date')->first();

    if (!$week) {
      return response()->json([
        'data'    => ['is_ready' => true],
        'status'  => 200,
        'message' => 'Chưa có tuần học nào, có thể tạo mới.',
      ]);
    }

    $done  = $week->tasks()->where('is_done', true)->count();
    $total = $week->tasks()->count();

    $is_ready = !$week->is_active && ($done === $total);

    return response()->json([
      'data'    => [
        'is_ready'        => $is_ready,
        'week_id'         => $week->id,
        'total_tasks'     => $total,
        'completed_tasks' => $done,
        'active'          => $week->is_active,
      ],
      'status'  => 200,
      'message' => $is_ready
        ? 'Bạn có thể tạo tuần tiếp theo.'
        : 'Tuần hiện tại chưa hoàn thành hoặc đang hoạt động.'
    ]);
  }

  public function getGroupedTasksOfWeek(Request $request, $weekId)
  {
    $week = LearningWeek::whereHas('profile', fn($q) => $q->where('user_id', Auth::id()))
      ->with('tasks')
      ->find($weekId);

    if (!$week) {
      return response()->json(['status' => 400, 'message' => 'Không tìm thấy tuần học này, vui lòng kiểm tra lại'], 400);
    }

    if ($week->tasks->isEmpty()) {
      return response()->json([
        'data'    => [
          'week'         => $week,
          'tasks_by_day' => [],
        ],
        'status'  => 200,
        'message' => 'Tuần học này không có task nào.',
      ]);
    }

    $tasks = $week->tasks()->with('exercises')->get();

    $grouped = $tasks->groupBy('day');

    return response()->json([
      'data'    => [
        'week'         => $week->makeHidden(['tasks']),
        'tasks_by_day' => $grouped,
      ],
      'status'  => 200,
      'message' => 'Lấy danh sách task đã nhóm theo ngày.',
    ]);
  }

  public function getGroupedTasksOfActiveWeek(Request $request, $profileId)
  {
    $profile = LearningProfile::where('user_id', Auth::id())->find($profileId);

    if (!$profile) {
      return response()->json(['status' => 400, 'message' => 'Không tìm thấy profile này'], 400);
    }

    $week = $profile->weeks()->where('is_active', true)->latest('start_date')->with('tasks')->first();

    if (!$week) {
      return response()->json([
        'data'    => null,
        'status'  => 404,
        'message' => 'Không tìm thấy tuần đang hoạt động.',
      ]);
    }

    $tasks = $week->tasks()->with('exercises')->get();

    $grouped = $tasks->groupBy('day');

    return response()->json([
      'data'    => [
        'week'         => $week->makeHidden(['tasks']),
        'tasks_by_day' => $grouped,
      ],
      'status'  => 200,
      'message' => 'Lấy danh sách task tuần hiện tại đã nhóm theo ngày.',
    ]);
  }

  public function buildUserPrompt($profile): string
  {
    // Build user profile information
    $userInfo = [
      "Age"                 => ($profile->user->age ?? '-'),
      "Name"                => ($profile->user->name ?? 'User'),
      "Gender"              => ($profile->user->gender ?? "Other"),
      "Interests"           => implode(', ', $profile->interests ?? []),
      "Occupation"          => ($profile->user->occupation ?? '-'),
      "Course Name"         => ($profile->course_name ?? '-'),
      "Primary Skill"       => $profile->primary_skill,
      "Skill Level"         => $profile->skill_level,
      "Secondary Skills"    => implode(', ', $profile->secondary_skills ?? []),
      "Learning Goals"      => $profile->goals,
      "Learning Style"      => $profile->learning_style,
      "Daily Learning Time" => $profile->daily_learning_time,
      "Preferred Resources" => implode(', ', $profile->preferred_resources ?? []),
      "Response Language"   => ($profile->language ?? 'English'),
    ];

    // Format the output
    $output = "";
    foreach ($userInfo as $key => $value) {
      $output .= "$key: $value\n";
    }

    // Add instruction for language response
    $output .= "\nIMPORTANT: RESPOND ONLY IN " . strtoupper($profile->language ?? 'English') . " LANGUAGE.\n";

    return $output;
  }

  public function getWeekHistory(Request $request)
  {
    $payload = $request->validate([
      'profile_id' => 'required|integer|exists:learning_profiles,id',
    ]);

    $profile = LearningProfile::where('user_id', Auth::id())->find($payload['profile_id']);

    if (!$profile) {
      return response()->json(['status' => 400, 'message' => 'Không tìm thấy profile này'], 400);
    }

    $weeks = $profile->weeks()->withCount(['tasks as total_tasks', 'tasks as done_tasks' => fn($q) => $q->where('is_done', true)])
      ->orderByDesc('start_date')
      ->get();

    return response()->json([
      'data'    => $weeks,
      'status'  => 200,
      'message' => 'Lấy danh sách tuần học thành công',
    ]);
  }

  public function exportWeekPdf(Request $request)
  {
    $payload = $request->validate([
      'week_id' => 'required|integer|exists:learning_weeks,id',
    ]);

    $week = LearningWeek::whereHas('profile', fn($q) => $q->where('user_id', Auth::id()))
      ->with('tasks')
      ->find($payload['week_id']);

    if (!$week) {
      return response()->json(['status' => 400, 'message' => 'Không tìm thấy tuần học này'], 400);
    }

    $pdf = Pdf::loadView('pdf.week-plan', ['week' => $week]);

    return $pdf->download("learning-week-{$week->id}.pdf");
  }

  public function emailWeekPdf(Request $request)
  {
    $payload = $request->validate([
      'week_id' => 'required|integer|exists:learning_weeks,id',
    ]);

    $week = LearningWeek::whereHas('profile', fn($q) => $q->where('user_id', Auth::id()))
      ->with('tasks', 'profile.user')
      ->find($payload['week_id']);

    if (!$week) {
      return response()->json(['status' => 400, 'message' => 'Không tìm thấy tuần học này'], 400);
    }

    $pdf = Pdf::loadView('pdf.week-plan', ['week' => $week])->output();

    Mail::send([], [], function ($message) use ($week, $pdf) {
      $message->to($week->profile->user->email)
        ->subject('Kế hoạch học tập tuần #' . $week->id)
        ->attachData($pdf, "learning-week-{$week->id}.pdf", [
          'mime' => 'application/pdf',
        ]);
    });

    return response()->json([
      'status'  => 200,
      'message' => 'Đã gửi kế hoạch học tập tuần qua email thành công',
    ]);
  }

  public function suggestNextWeekPrompt(Request $request)
  {
    $payload = $request->validate([
      'profile_id' => 'required|integer|exists:learning_profiles,id',
    ]);

    $profile = LearningProfile::where('user_id', Auth::id())->find($payload['profile_id']);

    if (!$profile) {
      return response()->json(['status' => 400, 'message' => 'Không tìm thấy profile này'], 400);
    }

    $lastWeek = $profile->weeks()->latest('start_date')->first();

    if (!$lastWeek) {
      return response()->json(['prompt' => $this->buildUserPrompt($profile)]);
    }

    $done  = $lastWeek->tasks()->where('is_done', true)->count();
    $total = $lastWeek->tasks()->count();

    $base       = $this->buildUserPrompt($profile);
    $summary    = "Tuần trước bạn đã hoàn thành {$done}/{$total} nhiệm vụ.";
    $suggestion = $done < $total / 2
      ? "Hãy nhẹ nhàng hơn tuần tới, tập trung những phần cốt lõi."
      : "Tuần tới có thể nâng độ khó hoặc thêm mini project.";

    return response()->json(['prompt' => $base . "\n\n" . $summary . "\n" . $suggestion]);
  }
}
