<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningTask;
use App\Models\LearningTaskExercise;
use App\Models\LearningWeek;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

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

    public function submit(Request $request)
    {
        $payload = $request->validate([
            'id'          => 'required|integer|exists:learning_task_exercises,id',
            'user_answer' => 'required|string',
        ]);

        $exercise = LearningTaskExercise::find($payload['id']);

        if ($exercise->is_submitted) {
            return response()->json([
                'message' => 'Câu hỏi này đã được nộp trước đó, làm câu khác nhé.',
                'status'  => 400,
            ], 400);
        }

        $exercise->end_time = now();

        $userAnswer = $request->input('user_answer');

        // Nếu là trắc nghiệm → so sánh trực tiếp
        if ($exercise->type === 'multiple_choice') {
            $isCorrect = strtolower(trim($userAnswer)) === strtolower(trim($exercise->answer));

            if ($isCorrect) {
                $exercise->is_correct = true;
                $exercise->user_score = $exercise->score;
            } else {
                $exercise->is_correct = false;
                $exercise->user_score = 0;
            }

            $exercise->user_answer = $exercise->options[$userAnswer] ?? null;
        } else {
            // Nếu là tự luận → hỏi AI để đánh giá tương đối
            $content = file_get_contents(storage_path('app/prompts/task-exercise-task.txt'));

            $prompts = str_replace('@exercise', $exercise->exercise, $content);
            $prompts = str_replace('@answer', $exercise->answer, $prompts);
            $prompts = str_replace('@userAnswer', $userAnswer, $prompts);
            $prompts = str_replace('@score', $exercise->score, $prompts);
            // $prompts = "Question: {$exercise->exercise}\nAnswer: {$exercise->answer}\nUser's Answer: {$userAnswer}\n\nIs the user's answer correct? Answer yes or no and explain why. Give a score from 0 to {{ $exercise->score }} based on completeness and correctness.";


            $aiResponse = Http::timeout(120)->withToken(config('services.openai.key'))
                ->post(config('services.openai.url'), [
                    'top_p'       => 1,
                    'model'       => config('services.openai.model'),
                    'temperature' => (double) config('services.openai.temperature'),
                    'messages'    => [
                        ['role' => 'system', 'content' => 'You are an evaluator for short written answers.'],
                        ['role' => 'user', 'content' => $prompts],
                        [
                            'role'    => 'user',
                            'content' => "Sử dụng ngôn ngữ: " . ($profile->user->language ?? 'VN') . " cho phần này."
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
            $user_score     = $parsed['score'] ?? 0;
            $ai_feedback    = $parsed['ai_feedback'] ?? '';
            $ai_evaluation  = $parsed['ai_evaluation'] ?? '';
            $ai_explanation = $parsed['ai_explanation'] ?? '';


            $exercise->ai_answer  = $ai_answer;
            $exercise->is_correct = $is_correct;
            $exercise->user_score = $user_score;

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

        $weekId = $payload['week_id'];

        $weekInfo = LearningWeek::where('id', $weekId)->first();

        if (!$weekInfo) {
            return response()->json([
                'message' => 'Không tìm thấy tuần học này, vui lòng kiểm tra lại.',
                'status'  => 400,
            ], 400);
        }

        $tasks = $weekInfo->tasks;

        $tasks = $tasks->map(function ($task) {
            return [
                'id'          => $task->id,
                'title'       => $task->title,
                'description' => $task->description,
                'exercises'   => $task->exercises->makeHidden(['answer'])->map(function ($exercise) {
                    return [
                        'id'           => $exercise->id,
                        'exercise'     => $exercise->exercise,
                        'answer'       => $exercise->answer,
                        'score'        => $exercise->score,
                        'user_answer'  => $exercise->user_answer,
                        'is_submitted' => $exercise->is_submitted,
                        'is_correct'   => $exercise->is_correct,
                        'user_score'   => $exercise->user_score,
                        'ai_feedback'  => $exercise->ai_feedback,
                    ];
                }),
            ];
        });

        // check xem đã làm hết chưa, nếu chưa thì không cho tóm tắt

        foreach ($tasks as $task) {
            foreach ($task['exercises'] as $exercise) {
                if (!$exercise['is_submitted']) {
                    return response()->json([
                        'status'  => 400,
                        'message' => 'Bạn chưa hoàn thành tất cả các bài tập trong tuần này.',
                    ], 400);
                }
            }
        }

        // tạo prompts từ danh sách bài tập, và gửi cho AI để tóm tắt
        $content = file_get_contents(storage_path('app/prompts/task-exercise-summary.txt'));

        $buildPrompts = "";
        $buildPrompts .= "Tuần học: {$weekInfo->title} - {$weekInfo->start_date} đến {$weekInfo->end_date}\n";
        $buildPrompts .= "Tóm tắt bài tập:\n";

        foreach ($tasks as $task) {
            $buildPrompts .= "Bài tập #" . $task['id'] . ": {$task['title']}\n";
            foreach ($task['exercises'] as $exercise) {
                $buildPrompts .= "- Câu hỏi: {$exercise['exercise']}\n";
                $buildPrompts .= "- Đáp án: {$exercise['answer']}\n";
                $buildPrompts .= "- Điểm tối đa: {$exercise['score']}\n";
                $buildPrompts .= "- Điểm của bạn: {$exercise['user_score']}\n";
                $buildPrompts .= "- Đánh giá của AI: {$exercise['ai_feedback']}\n";
            }
        }
        $buildPrompts .= "Sử dụng ngôn ngữ: " . ($weekInfo->profile->user->language ?? 'VN') . " cho phần này.\n";


        $aiResponse = Http::timeout(120)->withToken(config('services.openai.key'))
            ->post(config('services.openai.url'), [
                'top_p'       => 1,
                'model'       => config('services.openai.model'),
                'temperature' => (double) config('services.openai.temperature'),
                'messages'    => [
                    ['role' => 'system', 'content' => $content],
                    [
                        'role'    => 'user',
                        'content' => "Sử dụng ngôn ngữ: " . ($profile->user->language ?? 'VN') . " cho phần này."
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
            ], 400);
        }

        $data = $aiResponse->json('choices.0.message.content');

        $data = str_replace('```json', '', $data);
        $data = str_replace('```', '', $data);

        return $parsed = json_decode($data, true);

    }
}
