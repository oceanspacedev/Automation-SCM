<?php

namespace App\Services;

use App\Models\DataProgram;
use App\Models\ProgramReconciliationLog;
use App\Models\ProgramSubmission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pencocokan otomatis Data Program ↔ Form Program (cron tiap menit), dan WhatsApp ke telemarketing
 * untuk setiap baris yang BISA DI POTONG, sekali per baris (cron pada jam di Master Jadwal Otomatis).
 */
class AutoMatchService
{
    public const TRIGGERED_BY = 'auto_cron';

    /**
     * Purchase statuses that are already decided; such rows are not matched again.
     *
     * @var list<string>
     */
    private const SETTLED_PURCHASE_STATUSES = ['BISA DI POTONG', 'SUDAH POTONG'];

    /**
     * AR statuses before telemarketing has acted (later ones mean the dealer was already contacted).
     *
     * @var list<string>
     */
    private const AR_STATUSES_BEFORE_TELEMARKETING = ['', '-', 'BELUM POTONG'];

    public function __construct(
        protected ProgramReconciliationService $reconciler,
        protected WhatsAppService $whatsApp,
    ) {}

    /**
     * Match rows that cannot be cut yet against their Form Program submission.
     * Rows without a submission, or unchanged since their last check, are skipped so the history stays readable.
     *
     * @return array{evaluated: int, matched: int, can_cut: int, skipped_unchanged: int}
     */
    public function matchPendingRows(int $limit = 200): array
    {
        $summary = ['evaluated' => 0, 'matched' => 0, 'can_cut' => 0, 'skipped_unchanged' => 0];
        $batchId = (string) Str::uuid();

        $rows = DataProgram::query()
            ->where(fn ($q) => $q->whereNull('status_potong_purchase')
                ->orWhereNotIn('status_potong_purchase', self::SETTLED_PURCHASE_STATUSES))
            ->where(fn ($q) => $q->whereNull('status_potong_purchase')
                ->orWhere('status_potong_purchase', 'not like', '%DONE%'))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $dataProgram) {
            $submission = $this->reconciler->findMatchingSubmission($dataProgram);
            if (! $submission) {
                continue;
            }

            if ($this->unchangedSinceLastCheck($dataProgram, $submission)) {
                $summary['skipped_unchanged']++;

                continue;
            }

            $summary['evaluated']++;

            try {
                $result = $this->reconciler->reconcileSingle($dataProgram, $submission, false, $batchId, self::TRIGGERED_BY);
            } catch (\Throwable $e) {
                Log::warning("Auto-match Data Program #{$dataProgram->id} gagal: {$e->getMessage()}");

                continue;
            }

            if ($result['success']) {
                $summary['matched']++;
                if ($result['status'] === 'BISA DI POTONG') {
                    $summary['can_cut']++;
                }
            }
        }

        return $summary;
    }

    /**
     * Whether this row and submission were already compared and neither changed since.
     */
    private function unchangedSinceLastCheck(DataProgram $dataProgram, ProgramSubmission $submission): bool
    {
        $lastCheckedAt = ProgramReconciliationLog::withoutGlobalScopes(['area', 'brand'])
            ->where('data_program_id', $dataProgram->id)
            ->where('program_submission_id', $submission->id)
            ->max('created_at');

        if (! $lastCheckedAt) {
            return false;
        }

        $latestChange = max($dataProgram->updated_at?->getTimestamp() ?? 0, $submission->updated_at?->getTimestamp() ?? 0);

        return strtotime((string) $lastCheckedAt) >= $latestChange;
    }

    /**
     * WhatsApp the telemarketing of every row that can be cut and was not notified yet.
     *
     * @return array{notified: int, notify_failed: int}
     */
    public function notifyTelemarketing(int $limit = 200, float $sleepSeconds = 0.0): array
    {
        $summary = ['notified' => 0, 'notify_failed' => 0];

        $rows = DataProgram::query()
            ->where('status_potong_purchase', 'BISA DI POTONG')
            ->whereNull('telemarketing_notified_at')
            ->where(fn ($q) => $q->whereNull('status_potong_ar')
                ->orWhereIn('status_potong_ar', self::AR_STATUSES_BEFORE_TELEMARKETING))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $index => $dataProgram) {
            if ($index > 0 && $sleepSeconds > 0) {
                usleep((int) ($sleepSeconds * 1_000_000));
            }

            $result = $this->whatsApp->sendDataProgramClaimNotificationToTelemarketing($dataProgram, $dataProgram->telemarketing_phone);

            if ($result['success']) {
                $dataProgram->forceFill(['telemarketing_notified_at' => now()])->saveQuietly();
                $summary['notified']++;
            } else {
                // Dicoba lagi pada jadwal berikutnya
                Log::warning("WA telemarketing Data Program #{$dataProgram->id} gagal: {$result['message']}");
                $summary['notify_failed']++;
            }
        }

        return $summary;
    }
}
