<?php

namespace App\Http\Controllers;

use App\Services\DraftImportService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftImportController extends Controller
{
    protected DraftImportService $importService;

    public function __construct(DraftImportService $importService)
    {
        $this->importService = $importService;
    }

    public function import(Request $request): JsonResponse
    {
        // Check for raw PHP upload errors before Laravel validation
        if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['file']['error'];
            $phpUploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi batas upload PHP ('.ini_get('upload_max_filesize').').',
                UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas form HTML.',
                UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian. Silakan coba lagi.',
                UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diunggah.',
                UPLOAD_ERR_NO_TMP_DIR => 'Direktori temporary PHP tidak ditemukan. Silakan restart php artisan serve.',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk temporary PHP. Silakan restart php artisan serve.',
                UPLOAD_ERR_EXTENSION => 'Upload file dihentikan oleh ekstensi PHP.',
            ];

            return response()->json([
                'success' => false,
                'message' => $phpUploadErrors[$errCode] ?? 'Gagal mengunggah file (Kode error PHP: '.$errCode.').',
            ], 422);
        }

        if (! $request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan atau belum dipilih.',
            ], 422);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'])) {
            return response()->json([
                'success' => false,
                'message' => 'Format file .'.$ext.' tidak didukung. File harus berformat .xlsx, .xls, atau .csv.',
            ], 422);
        }

        try {
            $defaultType = $request->input('invoice_type', 'NPS FL');
            $result = $this->importService->import($file, $defaultType);

            return response()->json([
                'success' => true,
                'message' => 'Proses import selesai.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses import: '.$e->getMessage(),
            ], 500);
        }
    }
}
