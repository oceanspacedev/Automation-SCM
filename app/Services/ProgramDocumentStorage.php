<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Stores uploaded claim documents (CN, Agreement, Faktur Pajak, KTP/NPWP for a transfer) and gives their link.
 */
class ProgramDocumentStorage
{
    /**
     * Store a document in SeaweedFS / S3 under "<dealer>/<program>", or on the public disk when S3 is not configured
     * or the upload fails. The S3 disk does not throw: a failed upload returns false, which must not become a link to
     * the bucket root.
     */
    public function store(UploadedFile $file, string $prefix, string $dealerName, string $programName): string
    {
        $filename = $prefix.'_'.time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());

        if ($this->usesS3()) {
            try {
                $path = Storage::disk('s3')->putFileAs($this->folder($dealerName, $programName), $file, $filename);

                if ($path !== false) {
                    return Storage::disk('s3')->url($path);
                }

                Log::warning("Upload {$prefix} to S3 failed, fallback to local.");
            } catch (\Throwable $e) {
                Log::warning("Upload {$prefix} to S3 failed, fallback to local: ".$e->getMessage());
            }
        }

        return url('storage/'.$file->store('program_documents', 'public'));
    }

    /**
     * The S3 folder of a claim, e.g. "CV GRACE MANDIRI UTAMA/PROGRAM SEPTEMBER 2026" (characters a path can't hold replaced).
     */
    private function folder(string $dealerName, string $programName): string
    {
        $safe = fn (string $name, string $fallback) => preg_replace('/[\\\\\/:\*\?"<>\|]/', '_', trim($name)) ?: $fallback;

        return $safe($dealerName, 'DEALER').'/'.$safe($programName, 'PROGRAM');
    }

    private function usesS3(): bool
    {
        return ! empty(config('filesystems.disks.s3.key')) && ! empty(config('filesystems.disks.s3.bucket'));
    }
}
