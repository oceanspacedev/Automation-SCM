<?php

namespace App\Services;

use App\Area;
use App\Models\ArContact;
use App\Models\Customer;
use App\Models\DataProgram;
use App\Models\Dealer;
use App\Models\Draft;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\MasterProgram;
use App\Models\ProgramReconciliationLog;
use App\Models\ProgramSubmission;
use App\Models\Region;
use App\Models\WhatsAppLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

/**
 * Empties transaction data (Draft, Invoice, Data Program, Form Program) of an area and brand, with its history, and
 * master data (Dealer, Region, AR, Program, Badan Usaha). Brands, users, schedules and uploaded files are never touched.
 */
class DataResetService
{
    /**
     * Data that can be reset, with its label.
     *
     * @var array<string, string>
     */
    public const DATASETS = [
        'drafts' => 'Draft',
        'invoices' => 'Invoice',
        'data_programs' => 'Data Program',
        'program_submissions' => 'Form Program',
        'dealers' => 'Dealer',
        'regions' => 'Region',
        'ar_contacts' => 'AR',
        'master_programs' => 'Program',
        'customers' => 'Badan Usaha',
    ];

    /**
     * The datasets that are master data.
     *
     * @var list<string>
     */
    public const MASTER_DATASETS = ['dealers', 'regions', 'ar_contacts', 'master_programs', 'customers'];

    /**
     * @var array<string, class-string<Model>>
     */
    private const MODELS = [
        'drafts' => Draft::class,
        'invoices' => Invoice::class,
        'data_programs' => DataProgram::class,
        'program_submissions' => ProgramSubmission::class,
        'dealers' => Dealer::class,
        'regions' => Region::class,
        'ar_contacts' => ArContact::class,
        'master_programs' => MasterProgram::class,
        'customers' => Customer::class,
    ];

    /**
     * Which of the chosen area and brand narrow each dataset: dealers, regions and AR belong to an area, programs to a
     * brand (for every area), and Badan Usaha to neither.
     *
     * @var array<string, list<string>>
     */
    private const SCOPED_BY = [
        'drafts' => ['area', 'brand'],
        'invoices' => ['area', 'brand'],
        'data_programs' => ['area', 'brand'],
        'program_submissions' => ['area', 'brand'],
        'dealers' => ['area'],
        'regions' => ['area'],
        'ar_contacts' => ['area'],
        'master_programs' => ['brand'],
        'customers' => [],
    ];

    /**
     * Rows each dataset holds in the area and brand (null = every area / brand).
     *
     * @return array<string, int>
     */
    public function counts(?Area $area, ?string $brand): array
    {
        return collect(self::DATASETS)->map(fn (string $label, string $dataset) => $this->query($dataset, $area, $brand)->count())->all();
    }

    /**
     * Delete the chosen datasets of the area and brand (null = every area / brand).
     * Resetting drafts also deletes their invoices; resetting only invoices puts their drafts back to "ready".
     * Invoice email & WhatsApp logs and program matching history go with the rows they describe.
     *
     * @param  list<string>  $datasets
     * @return array<string, int> deleted rows per dataset, plus "history" for the removed logs
     */
    public function reset(array $datasets, ?Area $area, ?string $brand): array
    {
        if (in_array('drafts', $datasets, true)) {
            $datasets[] = 'invoices';
        }

        $datasets = array_values(array_intersect(array_keys(self::DATASETS), $datasets));

        // Baris yang sudah dihapus lunak (Form Program) ikut dihapus permanen, supaya sinkron ulang tidak bentrok row_hash
        $rows = fn (string $dataset): Builder => $this->query($dataset, $area, $brand)->withoutGlobalScope(SoftDeletingScope::class);
        // Yang dilaporkan: baris yang tampil di aplikasi (sama dengan jumlah sebelum reset)
        $purge = function (string $dataset) use ($area, $brand, $rows): int {
            $visible = $this->query($dataset, $area, $brand)->count();
            $rows($dataset)->forceDelete();

            return $visible;
        };

        return DB::transaction(function () use ($datasets, $area, $brand, $rows, $purge) {
            $deleted = array_fill_keys($datasets, 0) + ['history' => 0];

            if (in_array('invoices', $datasets, true)) {
                $deleted['history'] += EmailLog::query()->whereIn('invoice_id', $rows('invoices')->select('id'))->delete()
                    + WhatsAppLog::query()->whereIn('invoice_id', $rows('invoices')->select('id'))->delete();

                // Draft yang invoice-nya dihapus bisa di-generate lagi
                if (! in_array('drafts', $datasets, true)) {
                    Draft::withoutGlobalScopes(['area', 'brand'])
                        ->whereIn('id', $rows('invoices')->select('draft_id'))
                        ->where('status', 'invoiced')
                        ->update(['status' => 'ready']);
                }

                $deleted['invoices'] = $purge('invoices');
            }

            if (in_array('drafts', $datasets, true)) {
                $deleted['drafts'] = $purge('drafts');
            }

            $programDatasets = array_values(array_intersect(['data_programs', 'program_submissions'], $datasets));

            // Kedua sisi dihapus: seluruh riwayat pencocokan area & brand ini ikut, termasuk yang sudah tanpa tautan
            if (count($programDatasets) === 2) {
                $deleted['history'] += $this->scoped(ProgramReconciliationLog::query(), $area, $brand)->delete();
            } elseif ($programDatasets !== []) {
                $column = $programDatasets[0] === 'data_programs' ? 'data_program_id' : 'program_submission_id';
                $deleted['history'] += ProgramReconciliationLog::withoutGlobalScopes(['area', 'brand'])
                    ->whereIn($column, $rows($programDatasets[0])->select('id'))
                    ->delete();
            }

            foreach ($programDatasets as $dataset) {
                $deleted[$dataset] = $purge($dataset);
            }

            foreach (array_intersect(self::MASTER_DATASETS, $datasets) as $dataset) {
                $deleted[$dataset] = $purge($dataset);
            }

            return $deleted;
        });
    }

    /**
     * @return Builder<Model>
     */
    private function query(string $dataset, ?Area $area, ?string $brand): Builder
    {
        return $this->scoped(
            (self::MODELS[$dataset])::query(),
            in_array('area', self::SCOPED_BY[$dataset], true) ? $area : null,
            in_array('brand', self::SCOPED_BY[$dataset], true) ? $brand : null,
        );
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scoped(Builder $query, ?Area $area, ?string $brand): Builder
    {
        return $query->withoutGlobalScopes(['area', 'brand'])
            ->when($area, fn (Builder $q) => $q->where('area', $area->value))
            ->when($brand, fn (Builder $q) => $q->where('brand', $brand));
    }
}
