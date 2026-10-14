<?php

namespace App\Models;

use App\Area;
use App\Models\Concerns\BelongsToArea;
use App\Services\AreaContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramReconciliationLog extends Model
{
    use BelongsToArea;

    public const STATUS_MATCHED = 'MATCHED';

    public const STATUS_DOC_INCOMPLETE = 'DOC_INCOMPLETE';

    public const STATUS_NOMINAL_MISMATCH = 'NOMINAL_MISMATCH';

    public const STATUS_NO_MATCH = 'NO_MATCH';

    public const STATUS_ERROR = 'ERROR';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dp_amount' => 'float',
            'submission_amount' => 'float',
            'selisih' => 'float',
            'missing_docs' => 'array',
            'drive_transferred' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * A log belongs to the area of the Data Program / Form Program row it describes,
     * even when created outside a request (e.g. the AI analysis command).
     */
    protected function areaForNewRecord(): Area
    {
        $area = $this->data_program_id
            ? DataProgram::withoutGlobalScope('area')->whereKey($this->data_program_id)->value('area')
            : null;

        $area ??= $this->program_submission_id
            ? ProgramSubmission::withoutGlobalScope('area')->withTrashed()->whereKey($this->program_submission_id)->value('area')
            : null;

        return Area::fromInput($area) ?? app(AreaContext::class)->resolve();
    }

    /**
     * Data Program (56 kolom master spreadsheet) relation.
     */
    public function dataProgram(): BelongsTo
    {
        return $this->belongsTo(DataProgram::class, 'data_program_id')->withoutGlobalScope('area');
    }

    /**
     * Program Submission (Form Program) relation.
     */
    public function programSubmission(): BelongsTo
    {
        return $this->belongsTo(ProgramSubmission::class, 'program_submission_id')->withoutGlobalScope('area');
    }
}
