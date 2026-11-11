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
 * Draft Program has thousands of links) never reaches the queue's one-hour timeout. Every batch of stores is saved as
 * it finishes, so a check that fails or stops part-way keeps what it did and can be continued from there (resume()).
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
     * The same for an agreement check, which reports after every few documents: after this long without news it has
     * stopped, and it can be continued.
     */
    private const STALE_CHECK_AFTER_MINUTES = 20;

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
     * @param  string|null  $token  the import's current job; a job replaced by a newer one (e.g. after a resume) does nothing
     */
    public function __construct(public string $storedPath, public bool $replaceExisting, public ?string $type = null, public ?int $offset = null, public ?string $token = null) {}

    /**
     * Store the upload and queue its import; false while another import is still waiting or running.
     */
    public static function start(UploadedFile $file, bool $replaceExisting, string $startedBy, ?string $type = null): bool
    {
        if (self::isBusy()) {
            return false;
        }

        self::forgetUnfinishedCheck();
        $token = (string) Str::uuid();
        $storedPath = $file->storeAs('dealer-import', Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local');
        Cache::forever(self::STATUS_KEY, [
            'state' => 'queued',
            'type' => $type,
            'file_name' => $file->getClientOriginalName(),
            'started_by' => $startedBy,
            'progress' => null,
            'result' => null,
            'message' => null,
            'job' => $token,
            'queued_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        self::dispatch($storedPath, $replaceExisting, $type, null, $token);

        return true;
    }

    /**
     * Continue an agreement check that failed or stopped, from its last saved batch of stores and without reading the
     * file again; false when there is nothing to continue.
     */
    public static function resume(): bool
    {
        if (! self::canResume()) {
            return false;
        }

        $status = self::status();
        $token = (string) Str::uuid();
        Cache::forever(self::STATUS_KEY, [...$status, 'state' => 'queued', 'message' => null, 'job' => $token, 'updated_at' => now()->toIso8601String()]);

        self::dispatch($status['resume']['links'], false, 'agreements', $status['resume']['from'], $token);

        return true;
    }

    /**
     * Whether the latest agreement check stopped part-way (failed, or no news for a while) and can be continued.
     */
    public static function canResume(): bool
    {
        $links = self::status()['resume']['links'] ?? null;

        return $links !== null && ! self::isBusy() && Storage::disk('local')->exists($links);
    }

    /**
     * The latest import: state (queued, running, done, failed), type (dealers or agreements), progress and result.
     * An agreement check also has its counts so far and, while unfinished, where to continue (resume: links, from).
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
        $staleAfter = isset($status['resume']) ? self::STALE_CHECK_AFTER_MINUTES : self::STALE_AFTER_MINUTES;

        return in_array($status['state'] ?? null, ['queued', 'running'], true)
            && Carbon::parse($status['updated_at'])->gt(now()->subMinutes($staleAfter));
    }

    public function handle(DealerImportService $dealers, DealerAgreementImportService $agreements): void
    {
        if (! $this->isCurrent()) {
            return;
        }

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
                $this->report([
                    'progress' => ['done' => 0, 'total' => count($links), 'checked' => 0],
                    'counts' => DealerAgreementImportService::NO_COUNTS,
                    'resume' => ['links' => $linksPath, 'from' => 0],
                ]);

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
     * goes to a new job. Each finished batch is saved and remembered as the place to continue from; the counts add up
     * across jobs and become the result when the last store is done.
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
                    'resume' => ['links' => $linksPath, 'from' => $offset],
                ]);

                if ($offset < $total && $startedAt->diffInSeconds(now()) >= self::SECONDS_PER_JOB) {
                    $next = (string) Str::uuid();
                    $this->report(['job' => $next]);
                    self::dispatch($linksPath, false, 'agreements', $offset, $next);

                    return;
                }
            }
        } catch (Exception $e) {
            // File link disimpan: pemeriksaan bisa dilanjutkan dari kelompok toko terakhir yang tersimpan
            $this->report(['state' => 'failed', 'message' => $e->getMessage()]);

            return;
        }

        $this->report(['state' => 'done', 'result' => self::status()['counts'] ?? DealerAgreementImportService::NO_COUNTS, 'resume' => null]);
        Storage::disk('local')->delete($linksPath);
    }

    /**
     * The job stopped unexpectedly (e.g. ran out of memory or time). An agreement check keeps its links file, so it can
     * be continued from its last saved batch.
     */
    public function failed(?Throwable $exception): void
    {
        if (! $this->isCurrent()) {
            return;
        }

        $this->report(['state' => 'failed', 'message' => 'Import berhenti di tengah jalan: '.($exception?->getMessage() ?: 'proses import mati.')]);

        if ($this->offset === null) {
            Storage::disk('local')->delete($this->storedPath);
        }
    }

    /**
     * Whether this job is still the one the import is waiting on (jobs queued before tokens existed always are).
     */
    private function isCurrent(): bool
    {
        return $this->token === null || (self::status()['job'] ?? null) === $this->token;
    }

    /**
     * Remove the links file of an earlier agreement check that was never finished.
     */
    private static function forgetUnfinishedCheck(): void
    {
        if ($links = self::status()['resume']['links'] ?? null) {
            Storage::disk('local')->delete($links);
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function report(array $changes): void
    {
        if (! $this->isCurrent()) {
            return;
        }

        Cache::forever(self::STATUS_KEY, [...(self::status() ?? []), ...$changes, 'updated_at' => now()->toIso8601String()]);
    }
}
