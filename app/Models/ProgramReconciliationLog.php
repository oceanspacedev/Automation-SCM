<?php

namespace App\Models;

use App\Area;
use App\Models\Concerns\BelongsToArea;
use App\Models\Concerns\BelongsToBrand;
use App\Services\AreaContext;
use App\Services\BrandContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramReconciliationLog extends Model
{
    use BelongsToArea, BelongsToBrand;

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
        return Area::fromInput($this->describedRowValue('area')) ?? app(AreaContext::class)->resolve();
    }

    /**
     * A log belongs to the brand of the Data Program / Form Program row it describes.
     */
    protected function brandForNewRecord(): string
    {
        return $this->describedRowValue('brand') ?? app(BrandContext::class)->resolve();
    }

    /**
     * A column of the Data Program row this log describes, else of its Form Program row.
     */
    private function describedRowValue(string $column): ?string
    {
        $value = $this->data_program_id
            ? DataProgram::withoutGlobalScopes(['area', 'brand'])->whereKey($this->data_program_id)->value($column)
            : null;

        return $value ?? ($this->program_submission_id
            ? ProgramSubmission::withoutGlobalScopes(['area', 'brand'])->withTrashed()->whereKey($this->program_submission_id)->value($column)
            : null);
    }

    /**
     * Data Program (56 kolom master spreadsheet) relation.
     */
    public function dataProgram(): BelongsTo
    {
        return $this->belongsTo(DataProgram::class, 'data_program_id')->withoutGlobalScopes(['area', 'brand']);
    }

    /**
     * Program Submission (Form Program) relation.
     */
    public function programSubmission(): BelongsTo
    {
        return $this->belongsTo(ProgramSubmission::class, 'program_submission_id')->withoutGlobalScopes(['area', 'brand']);
    }
}
