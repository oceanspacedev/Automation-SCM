<?php

namespace Tests\Feature;

use App\Models\DataProgram;
use App\Models\ProgramSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_data_program_form_program_and_potong_status_per_region(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $dataProgram = fn (string $area, ?string $region, ?string $status, string $hash) => DataProgram::create([
            'area' => $area, 'region' => $region, 'status_potong_purchase' => $status, 'row_hash' => $hash, 'dealer_name' => 'TOKO',
        ]);
        $dataProgram('jabar', 'BIG CIREBON', 'BISA DI POTONG', 'r1');
        $dataProgram('jabar', 'big cirebon ', 'BELUM BISA POTONG', 'r2');
        $dataProgram('jabar', 'BIG CIREBON', 'SUDAH POTONG', 'r3');
        // Klaim yang ditransfer SCM dihitung terpisah dari yang dipotong
        $dataProgram('jabar', 'BIG CIREBON', DataProgram::DONE_TRANSFER, 'r6');
        $dataProgram('jateng', 'SEMARANG 1', null, 'r4');
        $dataProgram('jateng', null, 'BISA DI POTONG', 'r5');
        ProgramSubmission::create(['area' => 'jabar', 'region' => 'BIG CIREBON', 'dealer_name' => 'TOKO', 'row_hash' => 's1']);
        ProgramSubmission::create(['area' => 'jateng', 'region' => 'SEMARANG 1', 'dealer_name' => 'TOKO', 'row_hash' => 's2']);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('program.totals', ['data_program' => 6, 'form_program' => 2, 'bisa_potong' => 2, 'sudah_potong' => 1, 'done_transfer' => 1, 'belum_potong' => 2])
            ->assertJsonPath('program.regions', [
                ['area' => 'Jawa Barat', 'region' => 'BIG CIREBON', 'data_program' => 4, 'form_program' => 1, 'bisa_potong' => 1, 'sudah_potong' => 1, 'done_transfer' => 1, 'belum_potong' => 1],
                ['area' => 'Jawa Tengah', 'region' => 'SEMARANG 1', 'data_program' => 1, 'form_program' => 1, 'bisa_potong' => 0, 'sudah_potong' => 0, 'done_transfer' => 0, 'belum_potong' => 1],
                ['area' => 'Jawa Tengah', 'region' => 'TANPA REGION', 'data_program' => 1, 'form_program' => 0, 'bisa_potong' => 1, 'sudah_potong' => 0, 'done_transfer' => 0, 'belum_potong' => 0],
            ]);

        // One area only
        $this->getJson('/api/dashboard?area=jateng')
            ->assertOk()
            ->assertJsonPath('program.totals.data_program', 2)
            ->assertJsonPath('program.regions.*.region', ['SEMARANG 1', 'TANPA REGION']);
    }
}
