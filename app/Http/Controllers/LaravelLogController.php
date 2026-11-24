<?php

namespace App\Http\Controllers;

use App\Services\LaravelLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaravelLogController extends Controller
{
    public function index(Request $request, LaravelLogService $logs): JsonResponse
    {
        $this->ensureAdmin($request);

        $file = $request->query('file');
        $fileName = is_string($file) && $file !== '' ? $file : null;

        if ($fileName !== null && $logs->resolvePath($fileName) === null) {
            return response()->json([
                'success' => false,
                'message' => 'File log tidak ditemukan.',
            ], 404);
        }

        $result = $logs->read(
            requestedFile: $fileName,
            level: (string) $request->query('level', 'all'),
            search: (string) $request->query('search', ''),
            page: (int) $request->query('page', 1),
            perPage: (int) $request->query('per_page', 50),
        );

        return response()->json([
            'success' => true,
            ...$result,
        ])->header('Cache-Control', 'no-store');
    }

    public function download(Request $request, LaravelLogService $logs): BinaryFileResponse
    {
        $this->ensureAdmin($request);

        $fileName = (string) $request->query('file', '');
        if ($fileName === '') {
            $files = $logs->listFiles();
            $fileName = $files[0]['name'] ?? '';
        }

        $path = $logs->resolvePath($fileName);
        abort_if($path === null, 404, 'File log tidak ditemukan.');

        return response()->download($path, $fileName, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function clear(Request $request, LaravelLogService $logs): JsonResponse
    {
        $this->ensureAdmin($request);

        $fileName = (string) ($request->input('file') ?? $request->query('file') ?? '');
        if ($fileName === '') {
            $files = $logs->listFiles();
            $fileName = $files[0]['name'] ?? '';
        }

        if ($fileName === '' || ! $logs->clear($fileName)) {
            return response()->json([
                'success' => false,
                'message' => 'File log tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => "File {$fileName} berhasil dikosongkan.",
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Hanya Admin yang bisa mengakses Laravel Logs.');
    }
}
