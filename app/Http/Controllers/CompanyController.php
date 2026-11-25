<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Company::query()->orderBy('id')->get()->filter(fn (Company $company): bool => $company->canBeAccessedBy($request->user()))->values(),
            'current_id' => app(CompanyContext::class)->id(),
        ]);
    }
}
