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
 * Imports a file uploaded in Master Dealer in the background: the CV TOP master customer file ("dealers") or a Data
 * Program file with a sheet of Kode BT & AGREMENT for the stores' agreements ("agreements"). Without a type the file
 * decides: a sheet with Kode BT & AGREMENT means agreements, anything else the CV TOP file.
 * An agreement file is read once into its stores' links (kept next to the upload as JSON); the AI then checks them a few
 * stores at a time, and a job that has run for half an hour hands the rest to a new job, so a big file (the Jateng
 * Draft Program has thousands of links) never reaches the queue's one-hour timeout.
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

    /**
     * Stores whose agreements are checked before the job looks at the clock again.
     */
    private const STORES_PER_BATCH = 50;

    /**
     * After this long a job leaves the remaining stores of an agreement import to a new job.
     */
    private const SECONDS_PER_JOB = 1800;

    public int $tries = 1;

    public int $timeout = 3600;

    /**
     * @param  int|null  $offset  a continued agreement import: $storedPath is then its links file and this many stores are done
     */
    public function __construct(public string $storedPath, public bool $replaceExisting, public ?string $type = null, public ?int $offset = null) {}

    /**
     * Store the upload and queue its import; false while another import is still waiting or running.
     */
    public static function start(UploadedFile $file, bool $replaceExisting, string $startedBy, ?string $type = null): bool
    {
        if (self::isBusy()) {
            return false;
        }

        $storedPath = $file->storeAs('dealer-import', Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local');
        Cache::forever(self::STATUS_KEY, [
            'state' => 'queued',
            'type' => $type,
            'file_name' => $file->getClientOriginalName(),
            'started_by' => $startedBy,
            'progress' => null,
            'result' => null,
            'message' => null,
            'queued_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        self::dispatch($storedPath, $replaceExisting, $type);

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
        $this->report(['state' => 'running']);

        if ($this->offset !== null) {
            $this->importAgreements($agreements, $this->storedPath, $this->offset);

            return;
        }

        $path = Storage::disk('local')->path($this->storedPath);

        try {
            $sheet = $this->type === 'dealers' ? null : $agreements->findSheet($path);

            if ($this->type === 'agreements' && $sheet === null) {
                throw new Exception('Bukan file agreement: tidak ada sheet dengan kolom Kode BT dan AGREMENT di 10 baris teratas.');
            }

            if ($sheet !== null) {
                $this->report(['type' => 'agreements', 'sheet' => $sheet]);
                $links = $agreements->readLinks($path, $sheet);
                $linksPath = Str::beforeLast($this->storedPath, '.').'.links.json';
                Storage::disk('local')->put($linksPath, json_encode($links));
                $this->report(['progress' => ['done' => 0, 'total' => count($links), 'checked' => 0], 'counts' => DealerAgreementImportService::NO_COUNTS]);

                $this->importAgreements($agreements, $linksPath, 0);

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
     * Check the stores of an agreement import from the given one onwards, a batch at a time; after half an hour the rest
     * goes to a new job. The counts add up across jobs and become the result when the last store is done.
     */
    private function importAgreements(DealerAgreementImportService $agreements, string $linksPath, int $offset): void
    {
        $startedAt = now();

        try {
            $links = json_decode(Storage::disk('local')->get($linksPath), true);
            $total = count($links);

            while ($offset < $total) {
                $batch = array_slice($links, $offset, self::STORES_PER_BATCH, true);
                $checkedBefore = self::status()['progress']['checked'] ?? 0;
                $counts = $agreements->importLinks($batch, onProgress: fn (int $done, int $batchTotal, int $checked) => $this->report([
                    'progress' => ['done' => $offset + $done, 'total' => $total, 'checked' => $checkedBefore + $checked],
                ]));

                $offset += count($batch);
                $sums = self::status()['counts'] ?? DealerAgreementImportService::NO_COUNTS;
                foreach ($counts as $key => $count) {
                    $sums[$key] = ($sums[$key] ?? 0) + $count;
                }
                $this->report([
                    'progress' => ['done' => $offset, 'total' => $total, 'checked' => self::status()['progress']['checked'] ?? $checkedBefore],
                    'counts' => $sums,
                ]);

                if ($offset < $total && $startedAt->diffInSeconds(now()) >= self::SECONDS_PER_JOB) {
                    self::dispatch($linksPath, false, 'agreements', $offset);

                    return;
                }
            }

            $this->report(['state' => 'done', 'result' => self::status()['counts'] ?? DealerAgreementImportService::NO_COUNTS]);
        } catch (Exception $e) {
            $this->report(['state' => 'failed', 'message' => $e->getMessage()]);
        }

        Storage::disk('local')->delete($linksPath);
    }

    /**
     * The job stopped unexpectedly (e.g. ran out of memory or time).
     */
    public function failed(?Throwable $exception): void
    {
        $this->report(['state' => 'failed', 'message' => 'Import berhenti di tengah jalan: '.($exception?->getMessage() ?: 'proses import mati.')]);
        Storage::disk('local')->delete([$this->storedPath, Str::beforeLast($this->storedPath, '.').'.links.json']);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function report(array $changes): void
    {
        Cache::forever(self::STATUS_KEY, [...(self::status() ?? []), ...$changes, 'updated_at' => now()->toIso8601String()]);
    }
}
