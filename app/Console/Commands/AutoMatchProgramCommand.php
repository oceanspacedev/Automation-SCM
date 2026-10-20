<?php

namespace App\Console\Commands;

use App\Services\AutoMatchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('program:auto-match
    {--limit=200 : Batas baris Data Program yang diperiksa per jalan}')]
#[Description('Cocokkan otomatis Data Program dengan Form Program (tanpa WhatsApp; WA dikirim program:notify-telemarketing)')]
class AutoMatchProgramCommand extends Command
{
    /**
     * Execute the console command. The scheduler runs it every minute.
     */
    public function handle(AutoMatchService $autoMatch): int
    {
        $summary = $autoMatch->matchPendingRows(max(1, (int) $this->option('limit')));

        $this->info(sprintf(
            'Dicek %d baris (%d dilewati karena tidak berubah): %d cocok, %d BISA DI POTONG.',
            $summary['evaluated'],
            $summary['skipped_unchanged'],
            $summary['matched'],
            $summary['can_cut'],
        ));

        return self::SUCCESS;
    }
}
