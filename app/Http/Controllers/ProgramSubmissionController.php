<?php

namespace App\Http\Controllers;

use App\Models\ProgramSubmission;
use App\Services\ProgramSubmissionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramSubmissionController extends Controller
{
    public function __construct(
        protected ProgramSubmissionService $service
    ) {}

    /**
     * List program submissions with filtering & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProgramSubmission::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('dealer_name', 'like', "%{$search}%")
                    ->orWhere('id_real', 'like', "%{$search}%")
                    ->orWhere('sales_name', 'like', "%{$search}%")
                    ->orWhere('program_name', 'like', "%{$search}%");
            });
        }

        if ($region = trim((string) $request->input('region'))) {
            $query->where('region', $region);
        }

        if ($program = trim((string) $request->input('program'))) {
            $query->where('program_name', $program);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $submissions = $query->orderByDesc('id')->paginate($perPage);

        $regions = ProgramSubmission::whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region');

        $latestSubmission = ProgramSubmission::latest('updated_at')->first();

        return response()->json([
            'submissions' => $submissions,
            'regions' => $regions,
            'total_submissions' => ProgramSubmission::count(),
            'configured_webapp_url' => $this->service->getWebAppUrl(),
            'last_synced_at' => $latestSubmission?->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Trigger synchronization from Google Apps Script Web App.
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            $customUrl = $request->input('url');
            $limit = max(0, (int) $request->input('limit', 0));
            if ($customUrl) {
                $this->service->setWebAppUrl($customUrl);
            }

            $result = $this->service->syncFromWebAppUrl($customUrl, $limit);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan sinkronisasi: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Save Google Apps Script Web App URL config.
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $this->service->setWebAppUrl($request->input('url'));

        return response()->json([
            'success' => true,
            'message' => 'URL Google Apps Script Web App berhasil disimpan.',
            'configured_webapp_url' => $this->service->getWebAppUrl(),
        ]);
    }

    /**
     * Receive incoming webhook from Google Apps Script (e.g. onFormSubmit).
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            if (empty($payload)) {
                return response()->json(['success' => false, 'message' => 'Payload kosong.'], 400);
            }

            $submission = $this->service->saveWebhookPayload($payload);

            return response()->json([
                'success' => true,
                'message' => 'Data respon berhasil disimpan.',
                'id' => $submission->id,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses webhook: '.$e->getMessage(),
            ], 500);
        }
    }
}
