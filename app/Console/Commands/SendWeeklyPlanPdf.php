<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LearningWeek;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

class SendWeeklyPlanPdf extends Command
{
    protected $signature = 'weekly:email-plan';
    protected $description = 'Gửi kế hoạch tuần dưới dạng PDF tới email user mỗi Chủ Nhật';

    public function handle(): void
    {
        $weeks = LearningWeek::with(['tasks', 'profile.user'])
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now()->startOfWeek()) // tuần hiện tại
            ->get();

        foreach ($weeks as $week) {
            if (!$week->profile || !$week->profile->user)
                continue;

            $pdf = Pdf::loadView('pdf.week-plan', ['week' => $week])->output();

            Mail::send([], [], function ($message) use ($week, $pdf) {
                $message->to($week->profile->user->email)
                    ->subject('Kế hoạch học tuần hiện tại #' . $week->id)
                    ->attachData($pdf, "learning-week-{$week->id}.pdf", [
                        'mime' => 'application/pdf',
                    ]);
            });
        }

        $this->info('Đã gửi mail kế hoạch học tập tuần cho tất cả người dùng.');
    }
}
