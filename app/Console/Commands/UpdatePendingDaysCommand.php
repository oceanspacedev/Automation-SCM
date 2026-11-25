<?php

namespace App\Console\Commands;

use App\Models\DataProgram;
use App\Models\ProgramSubmission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('program:update-pending')]
#[Description('Perbarui otomatis Lama Pending (selisih hari ini - Tgl Share CN) dan Keterangan aging di Data Program & Form Program')]
class UpdatePendingDaysCommand extends Command
{
    public function handle(): int
    {
        $dpUpdated = 0;
        DataProgram::withoutGlobalScopes(['area', 'brand'])
            ->whereNotNull('tgl_share_cn')
            ->where('tgl_share_cn', '!=', '')
            ->chunkById(200, function ($rows) use (&$dpUpdated) {
                foreach ($rows as $row) {
                    $days = DataProgram::daysSince($row->tgl_share_cn);
                    if ($days === null) {
                        continue;
                    }

                    $updates = [];
                    if ($row->getRawOriginal('lama_pending') !== (string) $days) {
                        $updates['lama_pending'] = (string) $days;
                    }
                    $rawKeterangan = $row->getRawOriginal('keterangan');
                    if (empty($rawKeterangan) || in_array($rawKeterangan, ['LEBIH DARI 30 HARI', 'KURANG DARI 30 HARI'], true)) {
                        $aging = DataProgram::agingKeterangan($days);
                        if ($rawKeterangan !== $aging) {
                            $updates['keterangan'] = $aging;
                        }
                    }

                    if ($updates !== []) {
                        $row->timestamps = false;
                        $row->forceFill($updates)->saveQuietly();
                        $dpUpdated++;
                    }
                }
            });

        $subUpdated = 0;
        ProgramSubmission::withoutGlobalScopes(['area', 'brand'])
            ->whereNotNull('tgl_share_cn')
            ->where('tgl_share_cn', '!=', '')
            ->chunkById(200, function ($rows) use (&$subUpdated) {
                foreach ($rows as $row) {
                    $days = DataProgram::daysSince($row->tgl_share_cn);
                    if ($days === null) {
                        continue;
                    }

                    $updates = [];
                    if ($row->getRawOriginal('lama_pending') !== (string) $days) {
                        $updates['lama_pending'] = (string) $days;
                    }
                    $rawKeterangan = $row->getRawOriginal('keterangan');
                    if (empty($rawKeterangan) || in_array($rawKeterangan, ['LEBIH DARI 30 HARI', 'KURANG DARI 30 HARI'], true)) {
                        $aging = DataProgram::agingKeterangan($days);
                        if ($rawKeterangan !== $aging) {
                            $updates['keterangan'] = $aging;
                        }
                    }

                    if ($updates !== []) {
                        $row->timestamps = false;
                        $row->forceFill($updates)->saveQuietly();
                        $subUpdated++;
                    }
                }
            });

        $this->info("Berhasil memperbarui Lama Pending: {$dpUpdated} baris Data Program dan {$subUpdated} baris Form Program.");

        return self::SUCCESS;
    }
}
