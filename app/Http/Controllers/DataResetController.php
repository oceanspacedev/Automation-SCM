<?php

namespace App\Http\Controllers;

use App\Area;
use App\Models\AppSetting;
use App\Models\Brand;
use App\Services\DataResetService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DataResetController extends Controller
{
    /**
     * How many rows each dataset holds in the chosen area and brand, shown before resetting.
     */
    public function index(Request $request, DataResetService $reset): JsonResponse
    {
        $this->ensureAdmin($request);
        [$area, $brand] = $this->scope($request);

        return response()->json([
            'success' => true,
            'datasets' => DataResetService::DATASETS,
            'master_datasets' => DataResetService::MASTER_DATASETS,
            'counts' => $reset->counts($area, $brand),
            'password_set' => $this->resetPasswordHash() !== null,
        ]);
    }

    /**
     * Delete the chosen datasets of the area and brand; the admin has to enter the reset password.
     */
    public function destroy(Request $request, DataResetService $reset): JsonResponse
    {
        $this->ensureAdmin($request);

        $request->validate([
            'datasets' => ['required', 'array', 'min:1'],
            'datasets.*' => ['string', Rule::in(array_keys(DataResetService::DATASETS))],
            'area' => ['nullable', 'string', Rule::in(['all', ...array_column(Area::cases(), 'value')])],
            'brand' => ['nullable', 'string', Rule::in(['all', ...Brand::query()->pluck('slug')->all()])],
            'password' => ['required', 'string', $this->resetPasswordRule()],
        ], [
            'datasets.required' => 'Pilih minimal satu data yang mau direset.',
            'datasets.min' => 'Pilih minimal satu data yang mau direset.',
            'password.required' => 'Masukkan password reset.',
        ]);

        [$area, $brand] = $this->scope($request);
        $deleted = $reset->reset($request->input('datasets'), $area, $brand);

        Log::warning('Reset data oleh '.$request->user()->email, [
            'area' => $area?->value ?? 'all',
            'brand' => $brand ?? 'all',
            'deleted' => $deleted,
        ]);

        $summary = collect(DataResetService::DATASETS)
            ->filter(fn (string $label, string $dataset) => array_key_exists($dataset, $deleted))
            ->map(fn (string $label, string $dataset) => "{$deleted[$dataset]} {$label}")
            ->implode(', ');

        return response()->json([
            'success' => true,
            'message' => "Reset selesai: {$summary} dihapus.",
            'deleted' => $deleted,
            'counts' => $reset->counts($area, $brand),
        ]);
    }

    /**
     * Set or change the reset password (stored hashed); changing it needs the current one.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $isSet = $this->resetPasswordHash() !== null;

        $request->validate([
            'current_password' => $isSet ? ['required', 'string', $this->resetPasswordRule('Password lama salah.')] : ['nullable'],
            'new_password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ], [
            'current_password.required' => 'Masukkan password lama.',
            'new_password.required' => 'Masukkan password baru.',
            'new_password.min' => 'Password baru minimal 6 karakter.',
            'new_password.confirmed' => 'Ulangi password baru dengan sama persis.',
        ]);

        AppSetting::write(AppSetting::DATA_RESET_PASSWORD, Hash::make($request->input('new_password')));

        Log::warning('Password reset data '.($isSet ? 'diubah' : 'diatur').' oleh '.$request->user()->email);

        return response()->json([
            'success' => true,
            'message' => $isSet ? 'Password reset diubah.' : 'Password reset diatur.',
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Hanya Admin yang bisa mereset data.');
    }

    private function resetPasswordHash(): ?string
    {
        return AppSetting::read(AppSetting::DATA_RESET_PASSWORD) ?: null;
    }

    /**
     * The value must be the current reset password; fails while none is set.
     */
    private function resetPasswordRule(string $wrongMessage = 'Password reset salah.'): Closure
    {
        $hash = $this->resetPasswordHash();

        return function (string $attribute, mixed $value, Closure $fail) use ($hash, $wrongMessage) {
            if ($hash === null) {
                $fail('Password reset belum diatur. Atur dulu lewat tombol "Atur password reset".');
            } elseif (! Hash::check((string) $value, $hash)) {
                $fail($wrongMessage);
            }
        };
    }

    /**
     * The chosen area and brand; null means every area / brand.
     *
     * @return array{0: ?Area, 1: ?string}
     */
    private function scope(Request $request): array
    {
        $brand = (string) $request->input('brand');

        return [
            Area::fromInput($request->input('area')),
            $brand === '' || $brand === 'all' ? null : $brand,
        ];
    }
}
