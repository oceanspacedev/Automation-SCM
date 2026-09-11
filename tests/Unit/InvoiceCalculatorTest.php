<?php

namespace Tests\Unit;

use App\Services\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    private InvoiceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new InvoiceCalculator();
    }

    public function test_calculate_badan_pph_2_percent(): void
    {
        // Support Amount 10,000,000, BADAN
        // DPP = round(10,000,000 / 1.11, 0) = 9,009,009
        // DPP Lain = round(9,009,009 * 11 / 12, 0) = 8,258,258
        // PPN = round(8,258,258 * 0.12, 0) = 990,991
        // PPh = round(9,009,009 * 0.02, 0) = 180,180
        // Netpay = 9,009,009 + 990,991 - 180,180 = 9,819,820

        $res = $this->calculator->calculateFromValues(10000000, 'BADAN', 'BADAN');

        $this->assertEquals(0.02, $res['pph_rate']);
        $this->assertEquals(9009009, $res['dpp']);
        $this->assertEquals(8258258, $res['dpp_lain']);
        $this->assertEquals(990991, $res['ppn']);
        $this->assertEquals(180180, $res['pph']);
        $this->assertEquals(9819820, $res['netpay']);
    }

    public function test_calculate_badan_non_pph_2_percent(): void
    {
        $res = $this->calculator->calculateFromValues(10000000, 'BADAN NON', 'BADAN NON');

        $this->assertEquals(0.02, $res['pph_rate']);
        $this->assertEquals(9009009, $res['dpp']);
        $this->assertEquals(8258258, $res['dpp_lain']);
        $this->assertEquals(990991, $res['ppn']);
        $this->assertEquals(180180, $res['pph']);
        $this->assertEquals(9819820, $res['netpay']);
    }

    public function test_calculate_pribadi_pph_2_point_5_percent(): void
    {
        // PRIBADI without PKP -> DPP Lain = 0, PPN = 0
        // Support Amount 10,000,000
        // DPP = round(10,000,000 / 1.11, 0) = 9,009,009
        // DPP Lain = 0
        // PPN = 0
        // PPh = round(9,009,009 * 0.025, 0) = 225,225
        // Netpay = 9,009,009 + 0 - 225,225 = 8,783,784

        $res = $this->calculator->calculateFromValues(10000000, 'PRIBADI', 'PRIBADI');

        $this->assertEquals(0.025, $res['pph_rate']);
        $this->assertEquals(9009009, $res['dpp']);
        $this->assertEquals(0, $res['dpp_lain']);
        $this->assertEquals(0, $res['ppn']);
        $this->assertEquals(225225, $res['pph']);
        $this->assertEquals(8783784, $res['netpay']);
    }

    public function test_calculate_pribadi_pkp_dpp_lain(): void
    {
        // PRIBADI with PKP -> DPP Lain applies!
        $res = $this->calculator->calculateFromValues(10000000, 'PRIBADI', 'PRIBADI PKP');

        $this->assertEquals(0.025, $res['pph_rate']);
        $this->assertEquals(9009009, $res['dpp']);
        $this->assertEquals(8258258, $res['dpp_lain']);
        $this->assertEquals(990991, $res['ppn']);
        $this->assertEquals(225225, $res['pph']);
        $this->assertEquals(9774775, $res['netpay']);
    }

    public function test_calculate_bonus_pph_15_percent_and_full_dpp(): void
    {
        // Support Amount 10,000,000, BONUS
        // DPP = Support Amount = 10,000,000
        // DPP Lain = 0 (if not badan/pkp)
        // PPh = round(10,000,000 * 0.15, 0) = 1,500,000
        // Netpay = 10,000,000 + 0 - 1,500,000 = 8,500,000

        $res = $this->calculator->calculateFromValues(10000000, 'BONUS', 'PRIBADI');

        $this->assertEquals(0.15, $res['pph_rate']);
        $this->assertEquals(10000000, $res['dpp']);
        $this->assertEquals(1500000, $res['pph']);
        $this->assertEquals(8500000, $res['netpay']);
    }

    public function test_invalid_pph_type_falls_back_to_default_2_point_5_percent(): void
    {
        $res = $this->calculator->calculateFromValues(10000000, 'UNKNOWN_TYPE', 'PRIBADI');

        $this->assertEquals(0.025, $res['pph_rate']);
    }

    public function test_zero_support_amount(): void
    {
        $res = $this->calculator->calculateFromValues(0, 'BADAN', 'BADAN');

        $this->assertEquals(0, $res['support_amount']);
        $this->assertEquals(0, $res['dpp']);
        $this->assertEquals(0, $res['dpp_lain']);
        $this->assertEquals(0, $res['ppn']);
        $this->assertEquals(0, $res['pph']);
        $this->assertEquals(0, $res['netpay']);
    }
}
