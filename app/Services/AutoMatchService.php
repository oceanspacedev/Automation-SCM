<?php

namespace App\Services;

use App\Models\DataProgram;
use App\Models\ProgramApprovalLog;
use App\Models\ProgramReconciliationLog;
use App\Models\ProgramSubmission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pencocokan otomatis Data Program ↔ Form Program (cron tiap menit), dan WhatsApp ke telemarketing
 * untuk setiap baris yang BISA DI POTONG, sekali per baris, plus pengingat selama dealer belum menyetujui
 * (cron pada jam di Master Jadwal Otomatis).
 */
class AutoMatchService
{
    public const TRIGGERED_BY = 'auto_cron';

    /**
     * Cache key storing the latest DB state fingerprint after a full idle scan so cron skips redundant full scans.
     */
    public const CACHE_KEY_LAST_FINGERPRINT = 'auto_match:last_fingerprint';

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

    /**
     * AR statuses while the dealer has not approved yet: no answer, or postponed.
     *
     * @var list<string>
     */
    private const AR_STATUSES_AWAITING_DEALER = [...self::AR_STATUSES_BEFORE_TELEMARKETING, 'PENDING DEALER'];

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

        // Lewati pemindaian penuh bila tidak ada perubahan pada Data Program & Form Program sejak putaran penuh terakhir.
        $fingerprint = $this->currentDataFingerprint();
        if ($fingerprint !== null && Cache::get(self::CACHE_KEY_LAST_FINGERPRINT) === $fingerprint) {
            return $summary;
        }

        // Cek dulu brand mana yang punya Data Program pending agar tidak memuat seluruh Form Program saat idle.
        $pendingBrands = DataProgram::query()
            ->where(fn ($q) => $q->whereNull('status_potong_purchase')
                ->orWhereNotIn('status_potong_purchase', self::SETTLED_PURCHASE_STATUSES))
            ->where(fn ($q) => $q->whereNull('status_potong_purchase')
                ->orWhere('status_potong_purchase', 'not like', '%DONE%'))
            ->distinct()
            ->pluck('brand');

        if ($pendingBrands->isEmpty()) {
            $this->rememberFingerprint($fingerprint);

            return $summary;
        }

        // Muat pengajuan Form Program per scope (brand|area) sekali saja supaya seluruh baris pending
        // bisa dipindai tanpa batas 200 baris terbaru dan tanpa query N+1.
        $submissionQuery = ProgramSubmission::query()->orderByDesc('id');
        $nonEmptyBrands = $pendingBrands->filter(static fn ($brand): bool => is_string($brand) && $brand !== '')->values();
        if ($nonEmptyBrands->count() === $pendingBrands->count()) {
            $submissionQuery->whereIn('brand', $nonEmptyBrands->all());
        }

        $submissionsByScope = $submissionQuery
            ->get()
            ->groupBy(fn (ProgramSubmission $sub) => $sub->brand.'|'.$sub->areaEnum()->value);

        if ($submissionsByScope->isEmpty()) {
            $this->rememberFingerprint($fingerprint);

            return $summary;
        }

        $lastId = null;

        while ($summary['evaluated'] < $limit) {
            $query = DataProgram::query()
                ->where(fn ($q) => $q->whereNull('status_potong_purchase')
                    ->orWhereNotIn('status_potong_purchase', self::SETTLED_PURCHASE_STATUSES))
                ->where(fn ($q) => $q->whereNull('status_potong_purchase')
                    ->orWhere('status_potong_purchase', 'not like', '%DONE%'))
                ->orderByDesc('id')
                ->limit(200);

            if ($lastId !== null) {
                $query->where('id', '<', $lastId);
            }

            $rows = $query->get();
            if ($rows->isEmpty()) {
                break;
            }

            $lastId = $rows->last()->id;

            $candidates = [];
            foreach ($rows as $dataProgram) {
                $scopeKey = $dataProgram->brand.'|'.$dataProgram->areaEnum()->value;
                $scopeSubmissions = $submissionsByScope->get($scopeKey);
                if (! $scopeSubmissions || $scopeSubmissions->isEmpty()) {
                    continue;
                }

                $submission = $this->reconciler->findMatchingSubmission($dataProgram, $scopeSubmissions);
                if (! $submission) {
                    continue;
                }

                $candidates[] = ['data_program' => $dataProgram, 'submission' => $submission];
            }

            if ($candidates === []) {
                unset($rows);

                continue;
            }

            $lastCheckedMap = $this->loadLastCheckedTimestamps(
                array_map(static fn (array $pair): int => (int) $pair['data_program']->id, $candidates)
            );

            foreach ($candidates as $pair) {
                if ($summary['evaluated'] >= $limit) {
                    break;
                }

                /** @var DataProgram $dataProgram */
                $dataProgram = $pair['data_program'];
                /** @var ProgramSubmission $submission */
                $submission = $pair['submission'];

                $pairKey = "{$dataProgram->id}:{$submission->id}";
                if (isset($lastCheckedMap[$pairKey])) {
                    $latestChange = max($dataProgram->updated_at?->getTimestamp() ?? 0, $submission->updated_at?->getTimestamp() ?? 0);
                    if ($lastCheckedMap[$pairKey] >= $latestChange) {
                        $summary['skipped_unchanged']++;

                        continue;
                    }
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

            unset($rows, $candidates, $lastCheckedMap);
            gc_collect_cycles();
        }

        // Simpan fingerprint baru hanya jika seluruh baris pending selesai dipindai (tidak terpotong limit).
        if ($summary['evaluated'] < $limit) {
            $this->rememberFingerprint($this->currentDataFingerprint());
        }

        return $summary;
    }

    /**
     * Lightweight fingerprint of current Data Program & Form Program state (2 tiny aggregate SQL queries).
     */
    private function currentDataFingerprint(): ?string
    {
        try {
            $dp = DataProgram::query()
                ->selectRaw('COUNT(*) as cnt, MAX(id) as max_id, MAX(updated_at) as max_updated')
                ->first();
            $sub = ProgramSubmission::query()
                ->selectRaw('COUNT(*) as cnt, MAX(id) as max_id, MAX(updated_at) as max_updated')
                ->first();

            return implode('|', [
                (int) ($dp?->cnt ?? 0),
                (int) ($dp?->max_id ?? 0),
                (string) ($dp?->max_updated ?? ''),
                (int) ($sub?->cnt ?? 0),
                (int) ($sub?->max_id ?? 0),
                (string) ($sub?->max_updated ?? ''),
            ]);
        } catch (\Throwable $e) {
            Log::warning("Gagal membaca fingerprint auto-match: {$e->getMessage()}");

            return null;
        }
    }

    private function rememberFingerprint(?string $fingerprint): void
    {
        if ($fingerprint === null) {
            return;
        }

        try {
            Cache::put(self::CACHE_KEY_LAST_FINGERPRINT, $fingerprint, now()->addMinutes(10));
        } catch (\Throwable $e) {
            Log::warning("Gagal menyimpan fingerprint auto-match: {$e->getMessage()}");
        }
    }

    /**
     * Load the latest reconciliation timestamp per (data_program_id, program_submission_id) pair in a single query.
     *
     * @param  list<int>  $dataProgramIds
     * @return array<string, int>
     */
    private function loadLastCheckedTimestamps(array $dataProgramIds): array
    {
        if ($dataProgramIds === []) {
            return [];
        }

        $rows = ProgramReconciliationLog::withoutGlobalScopes(['area', 'brand'])
            ->whereIn('data_program_id', array_values(array_unique($dataProgramIds)))
            ->whereNotNull('program_submission_id')
            ->selectRaw('data_program_id, program_submission_id, MAX(created_at) as last_checked_at')
            ->groupBy('data_program_id', 'program_submission_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $timestamp = strtotime((string) $row->last_checked_at);
            if ($timestamp !== false) {
                $map["{$row->data_program_id}:{$row->program_submission_id}"] = $timestamp;
            }
        }

        return $map;
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
            ProgramApprovalLog::record($dataProgram, ProgramApprovalLog::TELEMARKETING_NOTIFIED, $result['success'], actor: ProgramApprovalLog::AUTOMATIC,
                recipient: trim(($dataProgram->telemarketing ?: '').' '.($dataProgram->telemarketing_phone ?: '')) ?: null, note: $result['message']);

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

    /**
     * Remind the telemarketing of rows the dealer has not approved yet (no answer, or postponed): $everyDays days after
     * the last WhatsApp and after the last postponement, at most $max times per row.
     * Only rows whose WhatsApp really went out (recorded in Riwayat Approval) are reminded, not old rows that were only
     * marked as notified when the WhatsApp job was introduced.
     *
     * @return array{reminded: int, remind_failed: int}
     */
    public function remindTelemarketing(int $everyDays, int $max, int $limit = 200, float $sleepSeconds = 0.0): array
    {
        $summary = ['reminded' => 0, 'remind_failed' => 0];

        if ($everyDays < 1 || $max < 1) {
            return $summary;
        }

        $lastDueDate = today()->subDays($everyDays);

        $rows = DataProgram::query()
            ->where('status_potong_purchase', 'BISA DI POTONG')
            ->where(fn ($q) => $q->whereNull('status_potong_ar')
                ->orWhereIn('status_potong_ar', self::AR_STATUSES_AWAITING_DEALER))
            ->where('telemarketing_reminder_count', '<', $max)
            ->whereDate('telemarketing_notified_at', '<=', $lastDueDate)
            ->whereHas('approvalLogs', fn ($q) => $q
                ->whereIn('event', [ProgramApprovalLog::TELEMARKETING_NOTIFIED, ProgramApprovalLog::TELEMARKETING_REMINDED])
                ->where('success', true))
            ->whereDoesntHave('approvalLogs', fn ($q) => $q
                ->where('event', ProgramApprovalLog::POSTPONED)
                ->whereDate('created_at', '>', $lastDueDate))
            ->orderBy('telemarketing_notified_at')
            ->limit($limit)
            ->get();

        foreach ($rows as $index => $dataProgram) {
            if ($index > 0 && $sleepSeconds > 0) {
                usleep((int) ($sleepSeconds * 1_000_000));
            }

            $reminderNumber = $dataProgram->telemarketing_reminder_count + 1;
            $result = $this->whatsApp->sendDataProgramClaimNotificationToTelemarketing($dataProgram, $dataProgram->telemarketing_phone, $reminderNumber);
            ProgramApprovalLog::record($dataProgram, ProgramApprovalLog::TELEMARKETING_REMINDED, $result['success'], actor: ProgramApprovalLog::AUTOMATIC,
                recipient: trim(($dataProgram->telemarketing ?: '').' '.($dataProgram->telemarketing_phone ?: '')) ?: null,
                note: "Pengingat ke-{$reminderNumber}. {$result['message']}");

            if ($result['success']) {
                $dataProgram->forceFill(['telemarketing_notified_at' => now(), 'telemarketing_reminder_count' => $reminderNumber])->saveQuietly();
                $summary['reminded']++;
            } else {
                // Dicoba lagi pada jadwal berikutnya
                Log::warning("Pengingat WA telemarketing Data Program #{$dataProgram->id} gagal: {$result['message']}");
                $summary['remind_failed']++;
            }
        }

        return $summary;
    }
}
