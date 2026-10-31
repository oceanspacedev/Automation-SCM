<?php

namespace App\Console\Commands;

use App\Models\AutomationSchedule;
use App\Services\AutoMatchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('program:notify-telemarketing
    {--limit=200 : Batas baris Data Program yang dikirimi (dan yang diingatkan) per jalan}
    {--sleep=0.5 : Jeda antar pengiriman WhatsApp dalam detik}')]
#[Description('Kirim WhatsApp ke telemarketing untuk baris Data Program yang BISA DI POTONG dan belum pernah dikirimi, lalu pengingat untuk yang belum disetujui dealer')]
class NotifyTelemarketingCommand extends Command
{
    /**
     * Execute the console command. When it runs is set in Master Jadwal Otomatis.
     */
    public function handle(AutoMatchService $autoMatch): int
    {
        $schedule = AutomationSchedule::forKey(AutomationSchedule::TELEMARKETING_WA);

        if (! config('services.auto_match.send_whatsapp', true)) {
            $result = 'WhatsApp telemarketing dimatikan lewat AUTO_MATCH_SEND_WA=false.';
            $this->warn($result);
            $schedule?->recordRun($result);

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $sleepSeconds = max(0.0, (float) $this->option('sleep'));

        $summary = $autoMatch->notifyTelemarketing($limit, $sleepSeconds);
        $reminders = $autoMatch->remindTelemarketing(
            $schedule?->reminder_every_days ?? AutomationSchedule::DEFAULT_REMINDER_EVERY_DAYS,
            $schedule?->reminder_max ?? AutomationSchedule::DEFAULT_REMINDER_MAX,
            $limit,
            $sleepSeconds,
        );

        $parts = [];
        if ($summary['notified'] + $summary['notify_failed'] > 0) {
            $parts[] = sprintf('WhatsApp telemarketing: %d terkirim, %d gagal (dicoba lagi berikutnya).', $summary['notified'], $summary['notify_failed']);
        }
        if ($reminders['reminded'] + $reminders['remind_failed'] > 0) {
            $parts[] = sprintf('Pengingat: %d terkirim, %d gagal (dicoba lagi berikutnya).', $reminders['reminded'], $reminders['remind_failed']);
        }

        $result = $parts === [] ? 'Tidak ada baris BISA DI POTONG yang perlu dikirimi WhatsApp.' : implode(' ', $parts);

        $this->info($result);
        $schedule?->recordRun($result);

        return self::SUCCESS;
    }
}
