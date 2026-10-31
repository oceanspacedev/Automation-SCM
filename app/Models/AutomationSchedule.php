<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AutomationScheduleFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Jadwal sebuah tugas otomatis (mis. WA ke telemarketing): setiap N menit atau pada jam tertentu, di hari yang dipilih.
 * Laravel scheduler mengecek tiap menit; tugas hanya jalan saat jadwal ini bilang sudah waktunya.
 */
class AutomationSchedule extends Model
{
    /** @use HasFactory<AutomationScheduleFactory> */
    use HasFactory;

    public const TELEMARKETING_WA = 'telemarketing_wa';

    public const MODE_INTERVAL = 'interval';

    public const MODE_TIMES = 'times';

    /**
     * Cache key the scheduler writes every minute, so the page can tell whether any schedule runs at all.
     */
    public const SCHEDULER_HEARTBEAT_KEY = 'scheduler:heartbeat';

    /**
     * Interval choices in minutes (each divides a day evenly, so runs land on the same clock times every day).
     *
     * @var list<int>
     */
    public const INTERVAL_CHOICES = [5, 10, 15, 30, 60, 120, 180, 360];

    /**
     * Days between reminders to telemarketing while the dealer has not approved a row (0: no reminders).
     *
     * @var list<int>
     */
    public const REMINDER_DAY_CHOICES = [0, 1, 2, 3, 5, 7];

    /**
     * How many reminders a row can get at most.
     *
     * @var list<int>
     */
    public const REMINDER_MAX_CHOICES = [1, 2, 3, 5];

    public const DEFAULT_REMINDER_EVERY_DAYS = 3;

    public const DEFAULT_REMINDER_MAX = 3;

    protected $guarded = ['id'];

    protected $appends = ['reminder_every_days', 'reminder_max'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'interval_minutes' => 'integer',
            'times' => 'array',
            'days' => 'array',
            'options' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * Note that the Laravel scheduler (schedule:work or cron schedule:run) ticked this minute.
     */
    public static function recordSchedulerHeartbeat(): void
    {
        Cache::forever(self::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String());
    }

    /**
     * When the scheduler last ticked; null when it never has.
     */
    public static function schedulerLastSeen(): ?CarbonInterface
    {
        $lastSeen = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);

        return $lastSeen ? Carbon::parse($lastSeen) : null;
    }

    /**
     * Whether the scheduler ticked in the last two minutes; when it did not, no schedule runs on its own.
     */
    public static function schedulerIsAlive(): bool
    {
        return (bool) static::schedulerLastSeen()?->isAfter(now()->subMinutes(2));
    }

    public static function forKey(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }

    /**
     * Whether the job with this key should run this minute; false when its schedule is missing or cannot be read.
     */
    public static function isDueNow(string $key): bool
    {
        try {
            return (bool) static::forKey($key)?->isDue(now());
        } catch (\Throwable $e) {
            Log::warning("Jadwal otomatis {$key} tidak bisa dibaca: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Whether the job runs at this minute (application timezone, WIB).
     */
    public function isDue(CarbonInterface $now): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $days = array_map('intval', $this->days ?? []);
        if ($days !== [] && ! in_array($now->dayOfWeekIso, $days, true)) {
            return false;
        }

        if ($this->mode === self::MODE_TIMES) {
            return in_array($now->format('H:i'), $this->times ?? [], true);
        }

        $interval = max(1, (int) $this->interval_minutes);

        return ($now->hour * 60 + $now->minute) % $interval === 0;
    }

    /**
     * Days after the last WhatsApp before the telemarketing is reminded of a row the dealer has not approved (0: never).
     */
    protected function reminderEveryDays(): Attribute
    {
        return Attribute::get(fn (): int => (int) ($this->options['reminder_every_days'] ?? self::DEFAULT_REMINDER_EVERY_DAYS));
    }

    /**
     * How many reminders a row gets at most.
     */
    protected function reminderMax(): Attribute
    {
        return Attribute::get(fn (): int => (int) ($this->options['reminder_max'] ?? self::DEFAULT_REMINDER_MAX));
    }

    /**
     * Note the outcome of a run, shown on the Jadwal Otomatis page.
     */
    public function recordRun(string $result): void
    {
        $this->forceFill(['last_run_at' => now(), 'last_result' => $result])->save();
    }
}
