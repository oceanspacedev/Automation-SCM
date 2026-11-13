<?php

namespace App\Http\Controllers;

use App\Area;
use App\Models\DataProgram;
use App\Models\Draft;
use App\Models\Invoice;
use App\Models\ProgramSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dashboard: Data Program & Form Program per region (with how many claims can be cut), plus the draft & invoice
 * metrics, for every brand and either every area or the chosen one. Open to every signed-in user (the first page after
 * signing in), so it is not a permission.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['area' => ['nullable', Rule::enum(Area::class)]]);
        $area = $validated['area'] ?? null;

        $query = fn (string $model): Builder => $model::withoutGlobalScopes(['area', 'brand'])->when($area, fn (Builder $q) => $q->where('area', $area));

        return response()->json([
            'program' => $this->programStats($query(DataProgram::class), $query(ProgramSubmission::class)),
            'total_draft' => $query(Draft::class)->count(),
            'draft_ready' => $query(Draft::class)->where('status', 'ready')->count(),
            'draft_error' => $query(Draft::class)->where('status', 'error')->count(),
            'total_invoice' => $query(Invoice::class)->count(),
            'invoice_dsa' => $query(Invoice::class)->where('invoice_type', 'DSA')->count(),
            'invoice_nps_fl' => $query(Invoice::class)->where('invoice_type', 'NPS FL')->count(),
            'invoice_regular' => $query(Invoice::class)->whereIn('invoice_type', ['REGULAR', 'REGULER'])->count(),
            'total_netpay' => (float) $query(Invoice::class)->sum('netpay'),
        ]);
    }

    /**
     * Data Program rows and Form Program submissions per area & region, with the Data Program rows that can be cut
     * (BISA DI POTONG), are already cut (SUDAH POTONG), were transferred instead (DONE TRANSFER), or cannot be cut yet.
     *
     * @param  Builder<DataProgram>  $dataPrograms
     * @param  Builder<ProgramSubmission>  $submissions
     * @return array{
     *     totals: array{data_program: int, form_program: int, bisa_potong: int, sudah_potong: int, done_transfer: int, belum_potong: int},
     *     regions: list<array{area: string, region: string, data_program: int, form_program: int, bisa_potong: int, sudah_potong: int, done_transfer: int, belum_potong: int}>
     * }
     */
    private function programStats(Builder $dataPrograms, Builder $submissions): array
    {
        $regions = [];
        $row = function (string $area, ?string $region) use (&$regions): string {
            $name = strtoupper(trim((string) $region));
            $name = $name === '' || $name === '-' ? 'TANPA REGION' : $name;
            $key = "{$area}|{$name}";
            $regions[$key] ??= ['area' => Area::from($area)->label(), 'region' => $name, 'data_program' => 0, 'form_program' => 0, 'bisa_potong' => 0, 'sudah_potong' => 0, 'done_transfer' => 0, 'belum_potong' => 0];

            return $key;
        };

        $dataProgramCounts = $dataPrograms->selectRaw('area, region, status_potong_purchase, count(*) as total')
            ->groupBy('area', 'region', 'status_potong_purchase')
            ->get();

        foreach ($dataProgramCounts as $count) {
            $key = $row($count->area, $count->region);
            $status = strtoupper(trim((string) $count->status_potong_purchase));
            // Klaim yang sudah ditransfer SCM dihitung terpisah dari yang sudah dipotong
            $bucket = match (true) {
                $status === 'BISA DI POTONG' => 'bisa_potong',
                str_starts_with($status, 'SUDAH POTONG') => 'sudah_potong',
                $status === DataProgram::DONE_TRANSFER => 'done_transfer',
                default => 'belum_potong',
            };

            $regions[$key]['data_program'] += (int) $count->total;
            $regions[$key][$bucket] += (int) $count->total;
        }

        foreach ($submissions->selectRaw('area, region, count(*) as total')->groupBy('area', 'region')->get() as $count) {
            $regions[$row($count->area, $count->region)]['form_program'] += (int) $count->total;
        }

        $regions = collect($regions)->sortBy(['area', 'region'])->values();
        $sum = fn (string $field): int => (int) $regions->sum($field);

        return [
            'totals' => [
                'data_program' => $sum('data_program'),
                'form_program' => $sum('form_program'),
                'bisa_potong' => $sum('bisa_potong'),
                'sudah_potong' => $sum('sudah_potong'),
                'done_transfer' => $sum('done_transfer'),
                'belum_potong' => $sum('belum_potong'),
            ],
            'regions' => $regions->all(),
        ];
    }
}
