<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Dealer;
use App\Models\ProgramSubmission;
use App\Services\AreaContext;
use App\Services\DealerAgreementImportService;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

#[Signature('dealers:fill-agreements
    {file? : File Data Program .xlsx (mis. DRAFT TEMPLATE PROGRAM JABAR) dengan kolom Kode BT & AGREMENT; tanpa file, agreement diambil dari Form Program}
    {--sheet=RAW JABAR : Nama sheet di file Data Program}
    {--parallel=8 : Jumlah dokumen yang dicek AI bersamaan}')]
#[Description('Isi agreement Master Dealer dari agreement terbaru tiap toko, dari Form Program Realme atau dari file Data Program (dicek AI)')]
class FillDealerAgreements extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AreaContext $areaContext, DealerAgreementImportService $agreements): int
    {
        return $this->argument('file')
            ? $this->fillFromDataProgram($agreements, (string) $this->argument('file'), (string) $this->option('sheet'))
            : $this->fillFromSubmissions($areaContext);
    }

    /**
     * Stores that already have an agreement are left as they are.
     */
    private function fillFromSubmissions(AreaContext $areaContext): int
    {
        $filled = 0;
        $storeNotFound = 0;

        ProgramSubmission::withoutGlobalScopes(['area', 'brand'])
            ->where('brand', Brand::DEFAULT_SLUG)
            ->where('agreement_url', 'like', 'http%')
            ->lazyByIdDesc(500)
            ->each(function (ProgramSubmission $submission) use ($areaContext, &$filled, &$storeNotFound) {
                $dealer = $areaContext->run($submission->areaEnum(), fn () => Dealer::findByIdReal($submission->id_real, $submission->dealer_name));

                if (! $dealer) {
                    $storeNotFound++;

                    return;
                }

                // Pengajuan diproses dari yang terbaru, jadi toko mendapat agreement terakhir yang dikirimnya
                if (! $dealer->agreement_url) {
                    $dealer->forceFill(['agreement_url' => $submission->agreement_url, 'agreement_updated_at' => $submission->created_at])->save();
                    $filled++;
                }
            });

        $this->info("Agreement terisi untuk {$filled} toko. {$storeNotFound} pengajuan dilewati karena tokonya tidak ditemukan di Master Dealer.");

        return self::SUCCESS;
    }

    /**
     * See DealerAgreementImportService for how each store's agreement is chosen.
     */
    private function fillFromDataProgram(DealerAgreementImportService $agreements, string $path, string $sheet): int
    {
        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $this->line('Mengecek isi agreement dengan AI...');
        $progress = null;

        try {
            $counts = $agreements->import($path, $sheet, (int) $this->option('parallel'), function (int $done, int $total) use (&$progress) {
                $progress ??= $this->output->createProgressBar($total);
                $progress->setProgress($done);
            });
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($progress instanceof ProgressBar) {
            $progress->finish();
            $this->newLine();
        }

        $this->info("Agreement dari sheet \"{$sheet}\": {$counts['filled']} toko diisi, {$counts['replaced']} diganti, {$counts['same']} sudah sama.");
        $this->line("{$counts['removed']} agreement dihapus dan {$counts['none']} toko tetap kosong: tidak ada link berupa agreement yang berisi nama toko, atau tanda tangan dengan cap bernama toko itu.");
        $this->line("{$counts['kept']} toko tetap memakai agreement-nya (tidak ada di file, mis. dikirim lewat Form Program).");
        $this->line("{$counts['not_found']} kode BT tidak ada di Master Dealer.");
        if ($counts['unchecked']) {
            $this->warn("{$counts['unchecked']} toko belum bisa dicek karena AI gagal membaca agreement-nya. Jalankan ulang perintah ini nanti; dokumen yang sudah dicek tidak dicek lagi.");
        }

        return self::SUCCESS;
    }
}
