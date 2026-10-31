<?php

namespace App\Jobs;

use App\Area;
use App\Services\DealerAgreementImportService;
use App\Services\DealerImportService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imports a file uploaded in Master Dealer in the background. A Data Program file (a sheet with Kode BT & AGREMENT)
 * fills the stores' agreements; any other file is read as the CV TOP master customer file.
 * Its progress and result are kept in the cache for the Master Dealer page (see status()).
 */
class ImportDealerFile implements ShouldQueue
{
    use Queueable;

    private const STATUS_KEY = 'dealer_import_status';

    /**
     * An import whose status hasn't changed for this long has stopped (e.g. the worker died), so a new one may start.
     */
    private const STALE_AFTER_MINUTES = 70;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public string $storedPath, public bool $replaceExisting) {}

    /**
     * Store the upload and queue its import; false while another import is still waiting or running.
     */
    public static function start(UploadedFile $file, bool $replaceExisting, string $startedBy): bool
    {
        if (self::isBusy()) {
            return false;
        }

        $storedPath = $file->storeAs('dealer-import', Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local');
        Cache::forever(self::STATUS_KEY, [
            'state' => 'queued',
            'type' => null,
            'file_name' => $file->getClientOriginalName(),
            'started_by' => $startedBy,
            'progress' => null,
            'result' => null,
            'message' => null,
            'queued_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        self::dispatch($storedPath, $replaceExisting);

        return true;
    }

    /**
     * The latest import: state (queued, running, done, failed), type (dealers or agreements), progress and result.
     *
     * @return array<string, mixed>|null
     */
    public static function status(): ?array
    {
        return Cache::get(self::STATUS_KEY);
    }

    public static function isBusy(): bool
    {
        $status = self::status();

        return in_array($status['state'] ?? null, ['queued', 'running'], true)
            && Carbon::parse($status['updated_at'])->gt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    public function handle(DealerImportService $dealers, DealerAgreementImportService $agreements): void
    {
        $path = Storage::disk('local')->path($this->storedPath);
        $this->report(['state' => 'running']);

        try {
            $sheet = $agreements->findSheet($path);

            if ($sheet !== null) {
                $this->report(['type' => 'agreements', 'sheet' => $sheet]);
                $counts = $agreements->import($path, $sheet, onProgress: fn (int $done, int $total, int $checked) => $this->report(['progress' => compact('done', 'total', 'checked')]));
                $this->report(['state' => 'done', 'result' => $counts]);

                return;
            }

            $this->report(['type' => 'dealers']);
            $result = $dealers->import($path, $this->replaceExisting);
            if ($result['skipped']) {
                $dealers->writeSkippedRows($result['skipped'], $result['header'], $dealers->skippedRowsPath());
            }

            $this->report(['state' => 'done', 'result' => [
                'imported' => $result['imported'],
                'per_area' => collect($result['per_area'])->mapWithKeys(fn (int $count, string $area) => [Area::from($area)->label() => $count])->all(),
                'regions_created' => $result['regions_created'],
                'duplicates' => count($result['duplicates']),
                'normalized_kode_bt' => $result['normalized_kode_bt'],
                'kept_original_kode_bt' => $result['kept_original_kode_bt'],
                'skipped' => array_map(fn (array $row) => Arr::only($row, ['row', 'kode_bt', 'dealer_name', 'reason']), $result['skipped']),
            ]]);
        } catch (Exception $e) {
            $this->report(['state' => 'failed', 'message' => $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($this->storedPath);
        }
    }

    /**
     * The job stopped unexpectedly (e.g. ran out of memory or time).
     */
    public function failed(?Throwable $exception): void
    {
        $this->report(['state' => 'failed', 'message' => 'Import berhenti di tengah jalan: '.($exception?->getMessage() ?: 'proses import mati.')]);
        Storage::disk('local')->delete($this->storedPath);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function report(array $changes): void
    {
        Cache::forever(self::STATUS_KEY, [...(self::status() ?? []), ...$changes, 'updated_at' => now()->toIso8601String()]);
    }
}
