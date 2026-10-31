<?php

namespace App\Services;

use App\Models\DataProgram;
use App\Models\ProgramApprovalLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Where a Data Program row is in the approval flow: cut-ready → WhatsApp to telemarketing → dealer approves (or
 * postpones) → WhatsApp to AR → cut by AR. The stage comes from the row's status columns, so rows that went through
 * the flow before it was recorded (or were changed in the spreadsheet) still show; the recorded steps add when and who.
 */
class ProgramApprovalFlow
{
    /**
     * @var array<string, string>
     */
    public const STAGES = [
        'waiting_telemarketing' => 'Belum dikirim ke telemarketing',
        'waiting_approval' => 'Menunggu persetujuan dealer',
        'postponed' => 'Ditunda dealer',
        'approved' => 'Disetujui · proses AR',
        'cut' => 'Sudah dipotong',
    ];

    private const READY = 'BISA DI POTONG';

    private const CUT = 'SUDAH POTONG';

    private const APPROVED = 'DEALER SETUJU (PROSES AR)';

    private const POSTPONED = 'PENDING DEALER';

    /**
     * AR statuses of a row that was cut: "DONE" from the AR link, "SUDAH POTONG" from the spreadsheet.
     *
     * @var list<string>
     */
    private const CUT_BY_AR = ['DONE', 'SUDAH POTONG'];

    /**
     * Rows that entered the flow: ready to be cut, already cut, sent to telemarketing, or answered by the dealer.
     *
     * @param  Builder<DataProgram>  $query
     * @return Builder<DataProgram>
     */
    public function inFlow(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereIn('status_potong_purchase', [self::READY, self::CUT])
            ->orWhereIn('status_potong_ar', [self::APPROVED, self::POSTPONED, ...self::CUT_BY_AR])
            ->orWhereNotNull('telemarketing_notified_at'));
    }

    /**
     * The rows of one stage; the same rules as stage(), so the counts match the rows' labels.
     *
     * @param  Builder<DataProgram>  $query
     * @return Builder<DataProgram>
     */
    public function whereStage(Builder $query, string $stage): Builder
    {
        $notCut = fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('status_potong_purchase')->orWhere('status_potong_purchase', '!=', self::CUT))
            ->where(fn (Builder $q) => $q->whereNull('status_potong_ar')->orWhereNotIn('status_potong_ar', self::CUT_BY_AR));
        $unanswered = fn (Builder $q) => $notCut($q)->where(fn (Builder $q) => $q->whereNull('status_potong_ar')->orWhereNotIn('status_potong_ar', [self::APPROVED, self::POSTPONED]));

        return match ($stage) {
            'cut' => $query->where(fn (Builder $q) => $q->where('status_potong_purchase', self::CUT)->orWhereIn('status_potong_ar', self::CUT_BY_AR)),
            'approved' => $notCut($query->where('status_potong_ar', self::APPROVED)),
            'postponed' => $notCut($query->where('status_potong_ar', self::POSTPONED)),
            'waiting_approval' => $unanswered($query->whereNotNull('telemarketing_notified_at')),
            'waiting_telemarketing' => $unanswered($query->where('status_potong_purchase', self::READY)->whereNull('telemarketing_notified_at')),
            default => $query,
        };
    }

    public function stage(DataProgram $row): string
    {
        $ar = strtoupper(trim((string) $row->status_potong_ar));

        return match (true) {
            strtoupper(trim((string) $row->status_potong_purchase)) === self::CUT || in_array($ar, self::CUT_BY_AR, true) => 'cut',
            $ar === self::APPROVED => 'approved',
            $ar === self::POSTPONED => 'postponed',
            $row->telemarketing_notified_at !== null => 'waiting_approval',
            default => 'waiting_telemarketing',
        };
    }

    /**
     * The five steps of the row, each done, current, waiting, failed (a WhatsApp that didn't go out) or postponed,
     * with the person in charge: the row's telemarketing for the WhatsApp to telemarketing and the dealer's answer
     * (given through the telemarketing's link), the region's AR for the WhatsApp to AR and the cut.
     *
     * @param  Collection<int, ProgramApprovalLog>  $logs  the row's recorded steps
     * @param  string|null  $arName  the AR of the row's region in Master AR (null: the default AR number)
     * @return list<array{key: string, label: string, state: string, at: ?string, date: ?string, person: ?string, role: ?string, actor: ?string, recipient: ?string, note: ?string}>
     */
    public function steps(DataProgram $row, Collection $logs, ?string $arName = null): array
    {
        $stage = $this->stage($row);
        $answered = in_array($stage, ['approved', 'postponed', 'cut'], true);
        $latest = fn (string $event, ?bool $success = null) => $logs
            ->filter(fn (ProgramApprovalLog $log) => $log->event === $event && ($success === null || $log->success === $success))
            ->sortByDesc('id')
            ->first();

        $telemarketing = $latest(ProgramApprovalLog::TELEMARKETING_NOTIFIED, true);
        $telemarketingFailed = $latest(ProgramApprovalLog::TELEMARKETING_NOTIFIED);
        $answer = $logs->filter(fn (ProgramApprovalLog $log) => in_array($log->event, [ProgramApprovalLog::APPROVED, ProgramApprovalLog::POSTPONED], true))->sortByDesc('id')->first();
        $ar = $latest(ProgramApprovalLog::AR_NOTIFIED, true);
        $arAttempt = $latest(ProgramApprovalLog::AR_NOTIFIED);
        $cut = $latest(ProgramApprovalLog::CUT);

        $telemarketingName = trim((string) $row->telemarketing) ?: null;
        $arName = trim((string) $arName) ?: 'AR default';

        $steps = [
            $this->step('ready', 'Bisa dipotong', true, note: $row->cek_dokumen ? "Cek dokumen: {$row->cek_dokumen}" : null,
                person: $row->cek_dokumen ? 'Dokumen '.strtolower(trim($row->cek_dokumen)) : null),
            $this->step('telemarketing', 'WA ke telemarketing', $telemarketing !== null || $row->telemarketing_notified_at !== null || $answered,
                log: $telemarketing ?? ($telemarketingFailed?->success === false ? $telemarketingFailed : null),
                at: $telemarketing?->created_at?->toIso8601String() ?? $row->telemarketing_notified_at?->toIso8601String(),
                failed: $telemarketing === null && $row->telemarketing_notified_at === null && ! $answered && $telemarketingFailed?->success === false,
                person: $telemarketingName, role: 'Telemarketing'),
            $this->step('approval', $stage === 'postponed' ? 'Ditunda dealer' : 'Persetujuan dealer', in_array($stage, ['approved', 'cut'], true),
                log: $answer, postponed: $stage === 'postponed', person: $telemarketingName, role: 'Telemarketing'),
            // WA ke AR terkirim otomatis saat dealer setuju; baris lama tanpa catatan dianggap sudah terkirim
            $this->step('ar', 'WA ke AR', $ar !== null || $stage === 'cut' || ($stage === 'approved' && $arAttempt === null),
                log: $ar ?? ($arAttempt?->success === false ? $arAttempt : null),
                failed: $ar === null && $stage === 'approved' && $arAttempt?->success === false,
                person: $arName, role: 'AR'),
            $this->step('cut', 'Dipotong AR', $stage === 'cut', log: $cut,
                note: $cut?->note ?? ($row->no_pembayaran ? "No. Pembayaran: {$row->no_pembayaran}" : null),
                at: $cut?->created_at?->toIso8601String(), date: $cut ? null : ($row->tgl_potong_tf ?: null),
                person: $arName, role: 'AR'),
        ];

        // Langkah pertama yang belum selesai adalah posisi baris saat ini
        foreach ($steps as $index => $step) {
            if ($step['state'] === 'waiting') {
                $steps[$index]['state'] = 'current';

                break;
            }
            if (in_array($step['state'], ['failed', 'postponed'], true)) {
                break;
            }
        }

        return $steps;
    }

    /**
     * @return array{key: string, label: string, state: string, at: ?string, date: ?string, person: ?string, role: ?string, actor: ?string, recipient: ?string, note: ?string}
     */
    private function step(
        string $key,
        string $label,
        bool $done,
        ?ProgramApprovalLog $log = null,
        ?string $at = null,
        ?string $note = null,
        ?string $date = null,
        bool $failed = false,
        bool $postponed = false,
        ?string $person = null,
        ?string $role = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'state' => match (true) {
                $postponed => 'postponed',
                $done => 'done',
                $failed => 'failed',
                default => 'waiting',
            },
            'at' => $at ?? $log?->created_at?->toIso8601String(),
            'date' => $date,
            'person' => $person,
            'role' => $role,
            'actor' => $log?->actor,
            'recipient' => $log?->recipient,
            'note' => $note ?? $log?->note,
        ];
    }
}
