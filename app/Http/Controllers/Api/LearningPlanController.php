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
use Illuminate\Support\Facades\Mail;

class LearningPlanController extends Controller
{
    // 1. Lấy danh sách profile của người dùng
    public function getProfiles(Request $request)
    {
        return response()->json([
            'data'    => LearningProfile::where('user_id', Auth::id())->get(),
            'status'  => 200,
            'message' => 'Lấy danh sách profile thành công',
        ]);
    }

    // 1b. Tạo mới learning profile
    public function createProfile(Request $request)
    {
        $validated = $request->validate([
            'course_name'         => 'required|string|max:255',
            'primary_skill'       => 'required|string',
            'skill_level'         => 'required|integer|min:0|max:100',
            'secondary_skills'    => 'array',
            'interests'           => 'array',
            'goals'               => 'nullable|string',
            'learning_style'      => 'nullable|string',
            'daily_learning_time' => 'nullable|string',
            'preferred_resources' => 'array',
            'custom_ai_prompt'    => 'nullable|string',
        ]);

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


    // 2. Gọi API và generate tuần học mới từ profile
    public function generateWeek(Request $request)
    {
        $payload = $request->validate([
            'profile_id' => 'required|integer|exists:learning_profiles,id',
        ]);

        $user    = User::findOrFail(Auth::id());
        $profile = LearningProfile::where('user_id', Auth::id())->find($payload['profile_id']);

        if (!$profile) {
            return response()->json(['status' => 422, 'message' => 'Không tìm thấy hồ sơ này'], 422);
        }

        $existing = $profile->weeks()
            ->whereDate('start_date', now()->startOfWeek())
            ->exists();

        if ($existing) {
            return response()->json([
                'data'    => null,
                'status'  => 409,
                'message' => 'Tuần học hiện tại đã tồn tại.',
            ]);
        }

        $lastWeek = $profile->weeks()->where('is_active', true)->latest('start_date')->first();
        if ($lastWeek && $lastWeek->tasks()->where('is_done', false)->exists()) {
            return response()->json([
                'data'    => null,
                'status'  => 400,
                'message' => 'Bạn cần hoàn thành tất cả task tuần trước trước khi tạo tuần mới.',
            ]);
        }

        if ($lastWeek) {
            $lastWeek->update(['is_active' => false]);
        }

        $content = file_get_contents(storage_path('app/prompts/learning-plan.txt'));
        $prompt  = $this->buildUserPrompt($profile);

        $content = str_replace('{{USER_PROFILE}}', $prompt, $content);

        $response = Http::timeout(0)->withToken(config('services.openai.key'))
            ->post(config('services.openai.url'), [
                'top_p'       => 1,
                'model'       => config('services.openai.model'),
                'temperature' => (double) config('services.openai.temperature'),
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => $content,
                    ],
                    // [
                    //     'role'    => 'user',
                    //     'content' => $prompt,
                    // ],
                    // [
                    //     'role'    => 'user',
                    //     'content' => "Hãy tạo một kế hoạch học tập cho tuần này, bao gồm các nhiệm vụ hàng ngày, thời gian dự kiến và tài liệu tham khảo. Đảm bảo rằng kế hoạch này phù hợp với trình độ kỹ năng và mục tiêu học tập của người dùng.",
                    // ],
                    [
                        'role'    => 'user',
                        'content' => "Sử dụng ngôn ngữ: " . ($profile->user->language ?? 'VN') . " cho kế hoạch này.",
                    ],
                ],
            ]);

        if (!$response->successful()) {
            return response()->json([
                'data'    => $response->json(),
                'status'  => 400,
                'message' => 'Có lỗi xảy ra khi thực hiện tạo kế hoạch, vui lòng thử lại.',
            ], 422);
        }

        $data = $response->json('choices.0.message.content');

        $data = str_replace('```json', '', $data);
        $data = str_replace('```', '', $data);

        $parsed = json_decode($data, true);

        if (!$parsed || !isset($parsed['weeklyPlan'])) {
            return response()->json([
                'data'    => null,
                'status'  => 400,
                'message' => 'Ôi không, có lỗi xảy ra khi tạo kế hoạch học tập, vui lòng thử lại.',
            ], 400);
        }

        $week = $profile->weeks()->create([
            'notes'      => $parsed['notes'] ?? null,
            'summary'    => $parsed['user']['summary'] ?? '',
            'user_id'    => $user->id,
            'is_active'  => true,
            'start_date' => now()->startOfWeek(),
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
                    'is_done'  => false,
                    'user_id'  => $user->id,
                ]);

                foreach ($task['exercises'] ?? [] as $ex) {
                    $taskModel->exercises()->create([
                        'exercise'     => $ex['exercise'],
                        'instructions' => $ex['instructions'] ?? null,
                        'answer'       => $ex['answer'] ?? null,
                        'difficulty'   => $ex['difficulty'] ?? 1,
                        'score'        => $ex['score'] ?? 1,
                        'type'         => $ex['type'] ?? 'written',
                        'options'      => isset($ex['options']) ? json_encode($ex['options']) : null,
                        'user_id'      => $user->id,
                        'user_answer'  => null,
                        'is_submitted' => false,
                    ]);
                }
            }
        }

        return response()->json([
            'data'    => $week,
            'status'  => 200,
            'message' => 'Đã tạo tuần học mới thành công, hãy tiếp tục học tập.',
        ]);
    }

    public function generateNextWeekFromPrevious(Request $request)
    {
        $payload = $request->validate([
            'profile_id' => 'required|integer|exists:learning_profiles,id',
        ]);

        $profile = LearningProfile::where('user_id', Auth::id())->find($payload['profile_id']);

        if (!$profile) {
            return response()->json(['status' => 422, 'message' => 'Không tìm thấy hồ sơ này, vui lòng thử lại.'], 422);
        }

        $lastWeek = $profile->weeks()->latest('start_date')->first();
        if (!$lastWeek) {
            return response()->json([
                'data'    => null,
                'status'  => 400,
                'message' => 'Chưa có tuần học trước đó, vui lòng tạo lại tuần học.',
            ], 400);
        }

        if ($lastWeek->is_active || $lastWeek->tasks()->where('is_done', false)->exists()) {
            return response()->json([
                'data'    => null,
                'status'  => 400,
                'message' => 'Cần hoàn thành toàn bộ tuần hiện tại trước khi tạo tuần tiếp theo.',
            ], 400);
        }

        $lastWeek->update(['is_active' => false]);

        // ✅ Lấy các bài tập đã nộp để gửi phân tích AI
        $exercises = $profile->weeks()
            ->with(['tasks.exercises' => fn($q) => $q->where('is_submitted', true)])
            ->get()
            ->flatMap(fn($w) => $w->tasks)
            ->flatMap(fn($t) => $t->exercises)
            ->map(fn($e) => [
                'exercise'    => $e->exercise,
                'user_answer' => $e->user_answer,
                'is_correct'  => $e->is_correct,
                'user_score'  => $e->user_score,
                'ai_feedback' => $e->ai_feedback,
                // 'ai_evaluation' => $e->ai_evaluation,
                // 'ai_explanation' => $e->ai_explanation,
                'difficulty'  => $e->difficulty,
                'score'       => $e->score,
            ])->values()->toArray();

        $analyzePrompt = [
            [
                'role'    => 'system',
                'content' => file_get_contents(storage_path('app/prompts/task-exercise-summary.txt')),
            ],
            [
                'role'    => 'user',
                'content' => json_encode(['exercises' => $exercises]),
            ],
        ];

        $summaryResponse = Http::timeout(120)->withToken(config('services.openai.key'))
            ->post(config('services.openai.url'), [
                'top_p'       => 1,
                'model'       => config('services.openai.model'),
                'temperature' => (double) config('services.openai.temperature'),
                'messages'    => $analyzePrompt,
            ]);

        $feedback = $summaryResponse->successful()
            ? json_decode($summaryResponse->json('choices.0.message.content'), true)
            : null;

        $enhancedPrompt = $this->buildUserPrompt($profile);
        $enhancedPrompt .= "\n\nAnalysis Summary:\n" . json_encode($feedback);

        $planResponse = Http::timeout(120)->withToken(config('services.openai.key'))
            ->post(config('services.openai.url'), [
                'top_p'       => 1,
                'model'       => config('services.openai.model'),
                'temperature' => (double) config('services.openai.temperature'),
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => file_get_contents(storage_path('app/prompts/learning-plan.txt')),
                    ],
                    [
                        'role'    => 'user',
                        'content' => $enhancedPrompt,
                    ],
                    [
                        'role'    => 'user',
                        'content' => "Sử dụng ngôn ngữ: " . ($profile->user->language ?? 'VN') . " cho kế hoạch này.",
                    ],
                ],
            ]);

        if (!$planResponse->successful()) {
            return response()->json([
                'data'    => $planResponse->json(),
                'status'  => 400,
                'message' => 'Có lỗi xảy ra khi thực hiện tạo kế hoạch, vui lòng thử lại.',
            ], 400);
        }

        $data = $planResponse->json('choices.0.message.content');
        $data = str_replace('```json', '', $data);
        $data = str_replace('```', '', $data);

        $parsed = json_decode($data, true);

        if (!$parsed || !isset($parsed['weeklyPlan'])) {
            return response()->json([
                'data'    => [
                    'errors' => null,
                ],
                'status'  => 400,
                'message' => 'Kết quả không hợp lệ từ AI.',
            ], 400);
        }

        $week = $profile->weeks()->create([
            'user_id'    => Auth::id(),
            'summary'    => $parsed['user']['summary'] ?? '',
            'notes'      => $parsed['notes'] ?? null,
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

                foreach ($task['exercises'] ?? [] as $ex) {
                    $taskModel->exercises()->create([
                        'exercise'     => $ex['exercise'],
                        'instructions' => $ex['instructions'] ?? null,
                        'answer'       => $ex['answer'] ?? null,
                        'difficulty'   => $ex['difficulty'] ?? 1,
                        'score'        => $ex['score'] ?? 1,
                        'type'         => $ex['type'] ?? 'written',
                        'options'      => isset($ex['options']) ? json_encode($ex['options']) : null,
                        'user_id'      => $taskModel->user_id,
                        'user_answer'  => null,
                        'is_submitted' => false,
                    ]);
                }
            }
        }

        return response()->json([
            'data'    => $week,
            'status'  => 200,
            'message' => 'Tạo tuần học tiếp theo thành công từ kết quả bài tập.',
        ]);
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

    // 4. Danh sách task theo tuần hiện tại
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

        $grouped = $week->tasks->groupBy('day');

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

        $tasks   = $week->tasks;
        $grouped = $week->tasks->groupBy('day');

        return response()->json([
            'data'    => [
                'week'         => $week->makeHidden(['tasks']),
                'tasks_by_day' => $grouped,
            ],
            'status'  => 200,
            'message' => 'Lấy danh sách task tuần hiện tại đã nhóm theo ngày.',
        ]);
    }


    // Helper để chuyển profile thành prompt ngữ cảnh
    public function buildUserPrompt($profile): string
    {
        return "" .
            "Age: " . ($profile->user->age ?? '-') . "\n" .
            "Name: " . ($profile->user->name ?? 'User') . "\n" .
            "Gender: " . ($profile->user->gender ?? "Other") . "\n" .
            "Interests: " . implode(', ', $profile->interests ?? []) . "\n" .
            "Occupation: " . ($profile->user->occupation ?? '-') . "\n" .
            "Course Name: " . ($profile->course_name ?? '-') . "\n" .
            "Primary Skill: {$profile->primary_skill}\n" .
            "Skill Level: {$profile->skill_level}\n" .
            "Secondary Skills: " . implode(', ', $profile->secondary_skills ?? []) . "\n" .
            "Learning Goals: {$profile->goals}\n" .
            "Learning Style: {$profile->learning_style}\n" .
            "Daily Learning Time: {$profile->daily_learning_time}\n" .
            "Preferred Resources: " . implode(', ', $profile->preferred_resources ?? []) . "\n";
    }

    // 7. Lịch sử tất cả các tuần của profile
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

    // 8. Export kế hoạch tuần thành PDF
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

    // 8b. Gửi kế hoạch tuần PDF qua email
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

    // 9. Gợi ý kế hoạch tuần tới dựa trên tiến độ tuần trước
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
