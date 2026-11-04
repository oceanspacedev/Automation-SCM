<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_cv_top_file_creates_one_active_telemarketing_account_per_number(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        User::factory()->create(['name' => 'Tele Lama', 'whatsapp' => '6281300000003', 'role' => 'telemarketing']);
        User::factory()->create(['name' => 'Budi', 'whatsapp' => '6281300000009', 'role' => 'scm']);

        $response = $this->postJson('/api/users/import', [
            'file' => $this->excel([
                ['ID Customer', 'Nama Customer', 'TELEMARKETING', 'NO. WA TELEMARKETING', 'Email'],
                // One row per store: the same telemarketing on several stores becomes one account
                ['BT00001', 'TOKO A', 'Siti Aminah', 81300000001, 'tokoa@example.com'],
                ['BT00002', 'TOKO B', 'SITI AMINAH', '0813-0000-0001', 'tokob@example.com'],
                ['BT00003', 'TOKO C', 'Rina', '081300000002', null],
                ['BT00004', 'TOKO D', 'Tele Lama', '081300000003', null],
                ['BT00005', 'TOKO E', 'Dewi', null, null],
                ['BT00006', 'TOKO F', 'Dewi', null, null],
                ['BT00007', 'TOKO G', 'Budi', '081300000004', null],
                ['BT00008', 'TOKO H', null, null, null],
            ]),
            'password' => 'rahasia123',
        ])->assertOk();

        $response->assertJsonPath('data.total_numbers', 4)
            ->assertJsonPath('data.created.*.name', ['Siti Aminah', 'Rina'])
            ->assertJsonPath('data.existing.0.account', 'Tele Lama')
            ->assertJsonPath('data.skipped.*.reason', ['Nomor WhatsApp kosong.', 'Nama sudah dipakai akun lain dengan nomor berbeda.']);

        $siti = User::where('whatsapp', '6281300000001')->firstOrFail();
        $this->assertSame(['Siti Aminah', 'telemarketing', true], [$siti->name, $siti->role, $siti->is_active]);
        $this->assertSame(User::getDefaultPermissionsForRole('telemarketing'), $siti->permissions);
        // The Email column of a store file is the store's, so the account is left without email
        $this->assertNull($siti->email);
        $this->assertTrue(Auth::validate(['name' => 'Siti Aminah', 'password' => 'rahasia123']));
        $this->assertSame(5, User::count());
    }

    public function test_a_plain_list_without_password_uses_its_email_and_a_random_password(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/users/import', ['file' => $this->excel([
            ['Nama', 'No WA', 'Email Telemarketing'],
            ['Rina', '081300000002', 'rina@example.com'],
        ])])->assertOk()->assertJsonPath('data.created.0.email', 'rina@example.com');

        $this->assertFalse(Auth::validate(['name' => 'Rina', 'password' => '']));
        $this->assertSame('6281300000002', User::where('email', 'rina@example.com')->value('whatsapp'));
    }

    public function test_a_peran_column_sets_each_role_and_the_chosen_role_fills_the_rest(): void
    {
        $scmUser = User::factory()->create(['role' => 'scm', 'is_active' => true, 'permissions' => User::getDefaultPermissionsForRole('scm')]);
        $this->actingAs($scmUser);

        $this->postJson('/api/users/import', [
            'file' => $this->excel([
                ['Nama', 'No WA', 'Email', 'Peran'],
                ['Andi', '081300000011', 'andi@example.com', 'AR'],
                ['Bela', '081300000012', null, null],
                ['Cici', '081300000013', null, 'Admin'],
                ['Dodi', '081300000014', null, 'Manajer'],
            ]),
            'role' => 'scm',
        ])->assertOk()
            ->assertJsonPath('data.created.*.role', ['ar', 'scm'])
            ->assertJsonPath('data.skipped.*.reason', ['Hanya Admin yang bisa membuat akun Admin.', 'Peran tidak dikenal (Admin, SCM, AR, atau Telemarketing).']);

        $andi = User::where('name', 'Andi')->firstOrFail();
        $this->assertSame(['ar', 'andi@example.com', User::getDefaultPermissionsForRole('ar')], [$andi->role, $andi->email, $andi->permissions]);
        $this->assertSame('scm', User::where('name', 'Bela')->value('role'));

        // Only an Admin may pick the Admin role for the whole file
        $this->postJson('/api/users/import', ['file' => $this->excel([['Nama', 'No WA'], ['Eka', '081300000015']]), 'role' => 'admin'])
            ->assertForbidden();
    }

    public function test_import_needs_the_name_and_number_columns_and_master_user_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/users/import', ['file' => $this->excel([['Dealer Name', 'Support Amount'], ['TOKO A', 1000]])])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->actingAs(User::factory()->create(['role' => 'telemarketing', 'permissions' => User::getDefaultPermissionsForRole('telemarketing')]));
        $this->postJson('/api/users/import', ['file' => $this->excel([['Nama', 'No WA'], ['Rina', '081300000002']])])
            ->assertForbidden();
        $this->assertFalse(User::where('name', 'Rina')->exists());
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function excel(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'tele_import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'Master Customer CV TOP.xlsx', null, null, true);
    }
}
