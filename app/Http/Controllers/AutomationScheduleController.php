<?php

namespace App\Http\Controllers;

use App\Models\AutomationSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class AutomationScheduleController extends Controller
{
    /**
     * Commands of the jobs that can be run from the Jadwal Otomatis page.
     *
     * @var array<string, string>
     */
    private const COMMANDS = [
        AutomationSchedule::TELEMARKETING_WA => 'program:notify-telemarketing',
    ];

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AutomationSchedule::query()->orderBy('id')->get(),
            'interval_choices' => AutomationSchedule::INTERVAL_CHOICES,
            'scheduler_alive' => AutomationSchedule::schedulerIsAlive(),
            'scheduler_last_seen' => AutomationSchedule::schedulerLastSeen()?->toIso8601String(),
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $schedule = AutomationSchedule::query()->where('key', $key)->firstOrFail();

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'mode' => ['required', Rule::in([AutomationSchedule::MODE_INTERVAL, AutomationSchedule::MODE_TIMES])],
            'interval_minutes' => ['required_if:mode,'.AutomationSchedule::MODE_INTERVAL, 'nullable', 'integer', Rule::in(AutomationSchedule::INTERVAL_CHOICES)],
            'times' => ['required_if:mode,'.AutomationSchedule::MODE_TIMES, 'array', 'max:24'],
            'times.*' => ['string', 'date_format:H:i', 'distinct'],
            'days' => ['array'],
            'days.*' => ['integer', 'between:1,7', 'distinct'],
        ], [
            'times.required_if' => 'Isi minimal satu jam jalan.',
            'times.*.date_format' => 'Jam harus berformat JJ:MM, misalnya 08:00.',
            'times.*.distinct' => 'Jam :input diisi lebih dari sekali.',
            'interval_minutes.in' => 'Pilih interval yang tersedia.',
        ]);

        $times = collect($validated['times'] ?? [])->unique()->sort()->values()->all();
        $days = collect($validated['days'] ?? [])->map(fn ($day) => (int) $day)->unique()->sort()->values()->all();

        $schedule->update([
            'is_active' => $validated['is_active'],
            'mode' => $validated['mode'],
            'interval_minutes' => $validated['mode'] === AutomationSchedule::MODE_INTERVAL ? $validated['interval_minutes'] : null,
            'times' => $validated['mode'] === AutomationSchedule::MODE_TIMES ? $times : [],
            // Ketujuh hari dipilih sama dengan setiap hari
            'days' => count($days) === 7 ? [] : $days,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal disimpan.',
            'data' => $schedule->fresh(),
        ]);
    }

    /**
     * Run a job now (outside its schedule), e.g. to try a new setting.
     */
    public function run(string $key): JsonResponse
    {
        $schedule = AutomationSchedule::query()->where('key', $key)->firstOrFail();
        $command = self::COMMANDS[$schedule->key] ?? abort(404);

        Artisan::call($command);

        return response()->json([
            'success' => true,
            'message' => trim(Artisan::output()) ?: 'Selesai dijalankan.',
            'data' => $schedule->fresh(),
        ]);
    }
}
