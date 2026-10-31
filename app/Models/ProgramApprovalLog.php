<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use App\Models\Concerns\BelongsToBrand;
use Database\Factories\ProgramApprovalLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One step of the approval flow of a Data Program row: WhatsApp to telemarketing (and reminders while the dealer has
 * not approved), the dealer's approval or postponement (through the telemarketing's link), WhatsApp to AR, and the cut by AR.
 */
class ProgramApprovalLog extends Model
{
    /** @use HasFactory<ProgramApprovalLogFactory> */
    use BelongsToArea, BelongsToBrand, HasFactory;

    public const TELEMARKETING_NOTIFIED = 'telemarketing_notified';

    public const TELEMARKETING_REMINDED = 'telemarketing_reminded';

    public const APPROVED = 'approved';

    public const POSTPONED = 'postponed';

    public const AR_NOTIFIED = 'ar_notified';

    public const CUT = 'cut';

    /**
     * Who acted through a WhatsApp confirmation link, by the link's role.
     *
     * @var array<string, string>
     */
    public const LINK_ACTORS = [
        'telemarketing' => 'Telemarketing (link WA)',
        'ar' => 'AR (link WA)',
    ];

    public const AUTOMATIC = 'Otomatis';

    protected $guarded = ['id'];

    protected $casts = [
        'success' => 'boolean',
    ];

    public function dataProgram(): BelongsTo
    {
        return $this->belongsTo(DataProgram::class)->withoutGlobalScopes(['area', 'brand']);
    }

    /**
     * Who a WhatsApp to AR about the row goes to, picked like WhatsAppService does: the given number, else the AR of
     * the row's region in Master AR, else the default AR number.
     */
    public static function arRecipient(?DataProgram $dataProgram, ?string $phone = null): ?string
    {
        if (filled($phone)) {
            return $phone;
        }

        if ($dataProgram === null) {
            return null;
        }

        $contact = ArContact::forRegion($dataProgram->areaEnum(), $dataProgram->region, $dataProgram->big_region);

        return $contact ? trim("{$contact->name} {$contact->whatsapp}") : 'AR default';
    }

    /**
     * Record a step for a Data Program row (and the Form Program submission it went through, if any).
     * Recording never stops the flow itself: a failure is only logged.
     */
    public static function record(
        ?DataProgram $dataProgram,
        string $event,
        bool $success = true,
        ?string $actor = null,
        ?string $recipient = null,
        ?string $note = null,
        ?ProgramSubmission $submission = null,
    ): void {
        $owner = $dataProgram ?? $submission;
        if ($owner === null) {
            return;
        }

        try {
            static::query()->create([
                'area' => $owner->area,
                'brand' => $owner->brand,
                'data_program_id' => $dataProgram?->id,
                'program_submission_id' => $submission?->id,
                'event' => $event,
                'success' => $success,
                'actor' => $actor,
                'recipient' => $recipient,
                'note' => $note,
            ]);
        } catch (Throwable $e) {
            Log::warning("Riwayat approval ({$event}) tidak tercatat: ".$e->getMessage());
        }
    }
}
