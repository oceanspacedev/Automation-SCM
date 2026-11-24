<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LaravelLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LaravelLogTest extends TestCase
{
    use RefreshDatabase;

    private string $tempLogDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempLogDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel_log_test_'.uniqid('', true);
        File::makeDirectory($this->tempLogDir, 0755, true);

        $this->app->bind(LaravelLogService::class, fn (): LaravelLogService => new LaravelLogService($this->tempLogDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempLogDir);

        parent::tearDown();
    }

    public function test_only_admins_can_access_laravel_logs(): void
    {
        $this->getJson('/api/laravel-logs')->assertUnauthorized();

        $scm = User::factory()->create(['role' => 'scm']);
        $this->actingAs($scm)->getJson('/api/laravel-logs')->assertForbidden();
        $this->actingAs($scm)->get('/api/laravel-logs/download')->assertForbidden();
        $this->actingAs($scm)->deleteJson('/api/laravel-logs')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/laravel-logs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('files', []);
    }

    public function test_admin_can_read_filter_and_search_parsed_log_entries_newest_first(): void
    {
        $logContent = implode("\n", [
            '[2026-11-24 08:00:00] production.INFO: Scheduler started cleanly',
            '[2026-11-24 09:00:01] production.WARNING: Scheduled command program:auto-match gagal dijalankan.',
            '[2026-11-24 10:15:30] production.ERROR: SQLSTATE[HY000]: Connection refused',
            '[stacktrace]',
            '#0 /var/www/app/Services/AutoMatchService.php(60): query()',
            '#1 {main}',
        ]);
        file_put_contents($this->tempLogDir.DIRECTORY_SEPARATOR.'laravel.log', $logContent);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/laravel-logs')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('current_file', 'laravel.log')
            ->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.error', 1)
            ->assertJsonPath('summary.warning', 1)
            ->assertJsonPath('summary.info', 1)
            ->assertJsonPath('entries.0.level', 'ERROR')
            ->assertJsonPath('entries.0.timestamp', '2026-11-24 10:15:30')
            ->assertJsonPath('entries.0.has_details', true)
            ->assertJsonPath('entries.1.level', 'WARNING')
            ->assertJsonPath('entries.2.level', 'INFO');

        $this->actingAs($admin)
            ->getJson('/api/laravel-logs?level=error')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('entries.0.level', 'ERROR');

        $this->actingAs($admin)
            ->getJson('/api/laravel-logs?search=AutoMatchService')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('entries.0.level', 'ERROR');
    }

    public function test_path_traversal_and_invalid_log_filenames_are_rejected(): void
    {
        file_put_contents($this->tempLogDir.DIRECTORY_SEPARATOR.'laravel.log', '[2026-11-24 08:00:00] local.INFO: OK');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson('/api/laravel-logs?file=../.env')->assertNotFound();
        $this->actingAs($admin)->getJson('/api/laravel-logs?file=../../composer.json')->assertNotFound();
        $this->actingAs($admin)->get('/api/laravel-logs/download?file=../.env')->assertNotFound();
        $this->actingAs($admin)->deleteJson('/api/laravel-logs?file=../.env')->assertNotFound();
    }

    public function test_admin_can_download_and_clear_a_log_file(): void
    {
        $path = $this->tempLogDir.DIRECTORY_SEPARATOR.'laravel.log';
        file_put_contents($path, "[2026-11-24 12:00:00] production.ERROR: Test failure\n");

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/api/laravel-logs/download?file=laravel.log')
            ->assertOk()
            ->assertDownload('laravel.log');

        $this->actingAs($admin)
            ->deleteJson('/api/laravel-logs?file=laravel.log')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertFileExists($path);
        $this->assertSame('', file_get_contents($path));
    }

    public function test_large_log_files_are_tail_truncated_safely(): void
    {
        $path = $this->tempLogDir.DIRECTORY_SEPARATOR.'laravel.log';
        $oldLine = '[2026-11-24 01:00:00] production.INFO: '.str_repeat('A', 400)."\n";
        $newLine = "[2026-11-24 12:00:00] production.ERROR: Latest critical failure\n";
        file_put_contents($path, str_repeat($oldLine, 20).$newLine);

        $service = new LaravelLogService($this->tempLogDir);
        $result = $service->read(requestedFile: 'laravel.log', maxReadBytes: 1024);

        $this->assertTrue($result['truncated']);
        $this->assertGreaterThan(1024, $result['file_size']);
        $this->assertSame('Latest critical failure', $result['entries'][0]['message']);
        $this->assertSame('ERROR', $result['entries'][0]['level']);
    }
}
