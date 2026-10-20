<?php

namespace App\Console\Commands;

use App\Models\AutomationSchedule;
use App\Services\AutoMatchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('program:notify-telemarketing
    {--limit=200 : Batas baris Data Program yang dikirimi per jalan}
    {--sleep=0.5 : Jeda antar pengiriman WhatsApp dalam detik}')]
#[Description('Kirim WhatsApp ke telemarketing untuk baris Data Program yang BISA DI POTONG dan belum pernah dikirimi')]
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

        $summary = $autoMatch->notifyTelemarketing(
            max(1, (int) $this->option('limit')),
            max(0.0, (float) $this->option('sleep')),
        );

        $result = $summary['notified'] + $summary['notify_failed'] === 0
            ? 'Tidak ada baris BISA DI POTONG yang perlu dikirimi WhatsApp.'
            : sprintf('WhatsApp telemarketing: %d terkirim, %d gagal (dicoba lagi berikutnya).', $summary['notified'], $summary['notify_failed']);

        $this->info($result);
        $schedule?->recordRun($result);

        return self::SUCCESS;
    }
}
