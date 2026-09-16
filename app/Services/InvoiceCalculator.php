<?php

namespace App\Services;

use App\Models\Draft;

class InvoiceCalculator
{
    /**
     * Calculate tax and payments based on Excel formulas.
     */
    public function calculateFromValues(float $supportAmount, ?string $pphType, ?string $npwpType = null): array
    {
        $pphRate = $this->determinePphRate($pphType);

        // DPP Calculation
        $normalizedPphType = strtoupper(trim((string) $pphType));
        if ($normalizedPphType === 'BONUS') {
            $dpp = $supportAmount;
        } else {
            $dpp = (float) round($supportAmount / 1.11, 0);
        }

        // DPP Lain Calculation
        if ($this->isDppLainEligible($npwpType, $pphType)) {
            $dppLain = (float) round(($dpp * 11) / 12, 0);
        } else {
            $dppLain = 0.0;
        }

        // PPN (12% of DPP Lain)
        $ppn = (float) round($dppLain * 0.12, 0);

        // PPh (Tarif PPh of DPP)
        $pph = (float) round($dpp * $pphRate, 0);

        // Netpay (DPP + PPN - PPh)
        $netpay = (float) round($dpp + $ppn - $pph, 0);

        return [
            'support_amount' => (float) round($supportAmount, 2),
            'pph_rate' => $pphRate,
            'dpp' => $dpp,
            'dpp_lain' => $dppLain,
            'ppn' => $ppn,
            'pph' => $pph,
            'netpay' => $netpay,
        ];
    }

    /**
     * Calculate based on a Draft model.
     */
    public function calculate(Draft $draft): array
    {
        return $this->calculateFromValues(
            (float) $draft->support_amount,
            $draft->pph_type,
            $draft->npwp_type
        );
    }

    /**
     * Determine PPh Rate based on rules.
     * BADAN       = 2%
     * BADAN NON   = 2%
     * PRIBADI     = 2.5%
     * BONUS       = 15%
     * Default     = 2.5%
     */
    public function determinePphRate(?string $pphType): float
    {
        $type = strtoupper(trim((string) $pphType));

        // If numeric like 0.02 or 0.025
        if (is_numeric($type)) {
            $val = (float) $type;
            if ($val > 0 && $val < 1) {
                return $val;
            }
            if ($val == 2) {
                return 0.02;
            }
            if ($val == 15) {
                return 0.15;
            }
            if ($val == 2.5) {
                return 0.025;
            }
        }

        return match ($type) {
            'BADAN', 'BADAN NON', '2%', '2', '0.02', '0,02' => 0.02,
            'BONUS', '15%', '15', '0.15', '0,15' => 0.15,
            'PRIBADI', '2.5%', '2,5%', '0.025', '0,025' => 0.025,
            default => 0.025,
        };
    }

    /**
     * Check if customer/tax qualifies for DPP Lain (BADAN or PRIBADI PKP).
     */
    public function isDppLainEligible(?string $npwpType, ?string $pphType): bool
    {
        $npwp = strtoupper(trim((string) $npwpType));
        $pph = strtoupper(trim((string) $pphType));

        if (str_contains($npwp, 'BADAN') || str_contains($npwp, 'PKP')) {
            return true;
        }

        if (str_contains($pph, 'BADAN') || str_contains($pph, 'PKP')) {
            return true;
        }

        return false;
    }

    /**
     * Compare Excel values stored on Draft vs System calculation.
     */
    public function compare(Draft $draft): array
    {
        $calculated = $this->calculate($draft);

        $fields = ['dpp', 'dpp_lain', 'ppn', 'pph', 'netpay'];
        $comparison = [];
        $allMatch = true;

        foreach ($fields as $field) {
            $excelVal = (float) $draft->{$field};
            $systemVal = (float) $calculated[$field];
            $diff = round(abs($excelVal - $systemVal), 2);
            $isMatch = ($diff < 1.0); // allow fractional rounding tolerance < 1

            if (! $isMatch) {
                $allMatch = false;
            }

            $comparison[$field] = [
                'excel' => $excelVal,
                'system' => $systemVal,
                'diff' => $diff,
                'status' => $isMatch ? 'MATCH' : 'DIFFERENT',
            ];
        }

        return [
            'is_matched' => $allMatch,
            'fields' => $comparison,
            'calculated' => $calculated,
        ];
    }
}
