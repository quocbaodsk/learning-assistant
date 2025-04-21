<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningTask;
use App\Models\LearningTaskExercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ExerciseSubmitController extends Controller
{
    public function submit(Request $request)
    {
        $payload = $request->validate([
            'id'          => 'required|integer|exists:learning_task_exercises,id',
            'task_id'     => 'required|integer|exists:learning_tasks,id',
            'user_answer' => 'required|string',
        ]);

        // $task     = LearningTask::where('id', $payload['id'])->where('user_id', auth()->user()->id)->first();
        $exercise = LearningTaskExercise::find($payload['id']);

        if ($exercise->is_submitted) {
            return response()->json([
                'message' => 'Câu hỏi này đã được nộp trước đó.',
                'status'  => 400,
            ]);
        }

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
                'score'       => $exercise->user_score,
                'answer'      => $exercise->answer,
                'correct'     => $exercise->is_correct,
                'your_answer' => $userAnswer,
                'ai_feedback' => $exercise->ai_feedback,
            ],
            'status'  => 200,
            'message' => 'Đã nộp bài tập thành công.',
        ]);
    }
}
