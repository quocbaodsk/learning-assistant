<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Models\{LearningProfile, LearningWeek, LearningTask};
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
            'data'    => LearningProfile::where('user_id', $request->user()->id)->get(),
            'status'  => 200,
            'message' => 'Lấy danh sách profile thành công',
        ]);
    }

    // 1b. Tạo mới learning profile
    public function createProfile(Request $request)
    {
        $validated = $request->validate([
            'primary_skill'       => 'required|string',
            'skill_level'         => 'required|integer|min:0|max:100',
            'secondary_skills'    => 'array',
            'goals'               => 'nullable|string',
            'learning_style'      => 'nullable|string',
            'daily_learning_time' => 'nullable|string',
            'preferred_resources' => 'array',
            'custom_ai_prompt'    => 'nullable|string',
        ]);

        $validated['user_id'] = $request->user()->id;
        $profile              = LearningProfile::create($validated);

        return response()->json(['data' => $profile, 'status' => 201, 'message' => 'Tạo hồ sơ mới thành công, tiếp tục tạo kế hoạch tuần học mới nhé!'], 201);
    }

    public function deleteProfile(Request $request, $profileId)
    {
        $profile = LearningProfile::where('user_id', $request->user()->id)->find($profileId);

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
    public function generateWeek(Request $request, $profileId)
    {
        $profile = LearningProfile::where('user_id', $request->user()->id)->find($profileId);

        if (!$profile) {
            return response()->json(['status' => 400, 'message' => 'Không tìm thấy profile này'], 400);
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

        $prompt = $this->buildUserPrompt($profile);

        $response = Http::withToken(config('services.openai.key'))
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
                        'content' => $prompt,
                    ],
                ],
            ]);

        if (!$response->successful()) {
            Log::info('Error response from OpenAI: ' . $response->body());

            return response()->json([
                'data'    => null,
                'status'  => 500,
                'message' => 'Có lỗi xảy ra khi thực hiện tạo kế hoạch, vui lòng thử lại.',
            ]);
        }

        $data   = $response->json('choices.0.message.content');
        $parsed = json_decode($data, true);

        if (!$parsed || !isset($parsed['weeklyPlan'])) {
            return response()->json([
                'data'    => null,
                'status'  => 500,
                'message' => 'Kết quả không hợp lệ từ AI.',
            ]);
        }

        $week = $profile->weeks()->create([
            'summary'    => $parsed['user']['summary'] ?? '',
            'notes'      => $parsed['notes'] ?? null,
            'start_date' => now()->startOfWeek(),
            'is_active'  => true,
        ]);

        foreach ($parsed['weeklyPlan'] as $day => $tasks) {
            foreach ($tasks as $task) {
                $week->tasks()->create([
                    'day'      => $day,
                    'task'     => $task['task'],
                    'duration' => $task['duration'],
                    'resource' => $task['resource'],
                    'type'     => $task['type'],
                    'focus'    => $task['focus'],
                    'is_done'  => false,
                ]);
            }
        }

        return response()->json([
            'data'    => $week,
            'status'  => 200,
            'message' => 'Tạo tuần học mới thành công.',
        ]);
    }

    public function generateNextWeekFromPrevious(Request $request, $profileId)
    {
        $profile = LearningProfile::where('user_id', $request->user()->id)->find($profileId);

        if (!$profile) {
            return response()->json(['status' => 400, 'message' => 'Không tìm thấy profile này'], 400);
        }

        $lastWeek = $profile->weeks()->latest('start_date')->first();
        if (!$lastWeek) {
            return response()->json([
                'data'    => null,
                'status'  => 400,
                'message' => 'Chưa có tuần học trước đó.',
            ]);
        }

        if ($lastWeek->is_active || $lastWeek->tasks()->where('is_done', false)->exists()) {
            return response()->json([
                'data'    => null,
                'status'  => 400,
                'message' => 'Cần hoàn thành toàn bộ tuần hiện tại trước khi tạo tuần tiếp theo.',
            ]);
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

        $lastWeek->update(['is_active' => false]);

        $prompt         = $this->buildUserPrompt($profile);
        $summary        = "Tuần trước người học đã hoàn thành toàn bộ {$lastWeek->tasks()->count()} nhiệm vụ.";
        $enhancedPrompt = $prompt . "\n\n" . $summary . " Hãy gợi ý kế hoạch tiếp theo phù hợp, nâng độ khó vừa phải.";

        $response = Http::withToken(config('services.openai.key'))
            ->post(config('services.openai.url'), [
                'top_p'       => 1,
                'model'       => config('services.openai.model'),
                'temperature' => config('services.openai.temperature'),
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => file_get_contents(storage_path('app/prompts/learning-plan.txt')),
                    ],
                    [
                        'role'    => 'user',
                        'content' => $enhancedPrompt,
                    ],
                ],
            ]);

        if (!$response->successful()) {
            Log::info('Error response from OpenAI: ' . $response->body());

            return response()->json([
                'data'    => null,
                'status'  => 500,
                'message' => 'Có lỗi xảy ra khi thực hiện tạo kế hoạch, vui lòng thử lại.',
            ]);
        }

        $data   = $response->json('choices.0.message.content');
        $parsed = json_decode($data, true);

        if (!$parsed || !isset($parsed['weeklyPlan'])) {
            return response()->json([
                'data'    => null,
                'status'  => 500,
                'message' => 'Kết quả không hợp lệ từ AI.',
            ]);
        }

        $week = $profile->weeks()->create([
            'summary'    => $parsed['user']['summary'] ?? '',
            'notes'      => $parsed['notes'] ?? null,
            'start_date' => now()->startOfWeek(),
            'is_active'  => true,
        ]);

        foreach ($parsed['weeklyPlan'] as $day => $tasks) {
            foreach ($tasks as $task) {
                $week->tasks()->create([
                    'day'      => $day,
                    'task'     => $task['task'],
                    'duration' => $task['duration'],
                    'resource' => $task['resource'],
                    'type'     => $task['type'],
                    'focus'    => $task['focus'],
                    'is_done'  => false,
                ]);
            }
        }

        return response()->json([
            'data'    => $week,
            'status'  => 200,
            'message' => 'Tạo tuần học tiếp theo thành công.',
        ]);
    }


    // 3. Cập nhật trạng thái task (hoàn thành / chưa)
    public function updateTaskStatus(Request $request, $taskId)
    {
        $task = LearningTask::whereHas('week', function ($query) use ($request) {
            $query->where('is_active', true)
                ->whereHas('profile', fn($q) => $q->where('user_id', $request->user()->id));
        })->find($taskId);

        if (!$task) {
            return response()->json(['status' => 400, 'message' => 'Không tìm thấy task này'], 400);
        }

        $task->update(['is_done' => (bool) $request->input('is_done')]);

        return response()->json([
            'data'    => $task,
            'status'  => 200,
            'message' => 'Cập nhật trạng thái thành công.',
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
                ->whereHas('profile', fn($q2) => $q2->where('user_id', $request->user()->id));
        })->find($taskId);

        if (!$task) {
            return response()->json(['status' => 400, 'message' => 'Không tìm thấy task này'], 400);
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
        $profile = LearningProfile::where('user_id', $request->user()->id)->findOrFail($profileId);
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
    public function getActiveTasks(Request $request, $profileId)
    {
        $profile = LearningProfile::where('user_id', $request->user()->id)->find($profileId);

        if (!$profile) {
            return response()->json(['status' => 400, 'message' => 'Không tìm thấy hồ sơ này, vui lòng kiểm tra lại'], 400);
        }


        $week = $profile->weeks()->where('is_active', true)->latest('start_date')->first();

        if (!$week) {
            return response()->json(['status' => 400, 'message' => 'Ôi không hệ thống không tìm thấy tuần học này'], 400);
        }

        $tasks = $week->tasks()->orderBy('day')->get();

        return response()->json([
            'data'    => ['week' => $week, 'tasks' => $tasks],
            'status'  => 200,
            'message' => 'Lấy danh sách công việc thành công',
        ]);
    }

    public function getTasksOfWeek(Request $request, $weekId)
    {
        $week = LearningWeek::whereHas('profile', fn($q) => $q->where('user_id', $request->user()->id))
            ->find($weekId);

        if (!$week) {
            return response()->json(['status' => 400, 'message' => 'Không tìm thấy tuần học này, vui lòng kiểm tra lại'], 400);
        }

        return response()->json([
            'data'    => [
                'week'  => $week,
                'tasks' => $week->tasks()->orderBy('day')->get(),
            ],
            'status'  => 200,
            'message' => 'Lấy danh sách task của tuần thành công.',
        ]);
    }

    public function getGroupedTasksOfWeek(Request $request, $weekId)
    {
        $week = LearningWeek::whereHas('profile', fn($q) => $q->where('user_id', $request->user()->id))
            ->with('tasks')
            ->findOrFail($weekId);

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
        $profile = LearningProfile::where('user_id', $request->user()->id)->findOrFail($profileId);
        $week    = $profile->weeks()->where('is_active', true)->latest('start_date')->with('tasks')->first();

        if (!$week) {
            return response()->json([
                'data'    => null,
                'status'  => 404,
                'message' => 'Không tìm thấy tuần đang hoạt động.',
            ]);
        }

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
    protected function buildUserPrompt($profile): string
    {
        $preferred = collect($profile->preferred_resources ?? [])->filter()->values()->all();

        return "Tôi là người dùng muốn học " . $profile->primary_skill . ". Tôi có trình độ " . $profile->skill_level . "/100, học theo kiểu " . ($profile->learning_style ?? 'Không rõ') . ", mỗi ngày học " . ($profile->daily_learning_time ?? 'Không rõ') . ". Tôi thích học qua: " . implode(', ', $preferred) . ". Mục tiêu của tôi: " . ($profile->goals ?? 'Không rõ') . ".";
    }

    //
    // 7. Lịch sử tất cả các tuần của profile
    public function getWeekHistory(Request $request, $profileId)
    {
        $profile = LearningProfile::where('user_id', $request->user()->id)->find($profileId);

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
    public function exportWeekPdf(Request $request, $weekId)
    {
        $week = LearningWeek::whereHas('profile', fn($q) => $q->where('user_id', $request->user()->id))
            ->with('tasks')
            ->find($weekId);

        if (!$week) {
            return response()->json(['status' => 400, 'message' => 'Không tìm thấy tuần học này'], 400);
        }

        $pdf = Pdf::loadView('pdf.week-plan', ['week' => $week]);

        return $pdf->download("learning-week-{$week->id}.pdf");
    }

    // 8b. Gửi kế hoạch tuần PDF qua email
    public function emailWeekPdf(Request $request, $weekId)
    {
        $week = LearningWeek::whereHas('profile', fn($q) => $q->where('user_id', $request->user()->id))
            ->with('tasks', 'profile.user')
            ->find($weekId);

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
    public function suggestNextWeekPrompt(Request $request, $profileId)
    {
        $profile = LearningProfile::where('user_id', $request->user()->id)->find($profileId);

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
