<?php

namespace App\Console\Commands;

use App\Models\AutomationSchedule;
use App\Models\Company;
use App\Services\CompanyContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunCompanyTask extends Command
{
    protected $signature = 'company:run {company} {task} {--due=}';

    protected $description = 'Run an automation task within a company boundary';

    public function handle(): int
    {
        $company = Company::query()->where('slug', $this->argument('company'))->firstOrFail();
        $tasks = [
            'match' => ['program:auto-match', []],
            'analyze' => ['program:auto-analyze-ai', ['--year' => now()->year, '--limit' => 5, '--sleep' => 0.3]],
            'notify' => ['program:notify-telemarketing', ['--limit' => 50]],
            'remind' => ['invoices:remind-telemarketing-upload', ['--limit' => 50]],
            'pending' => ['program:update-pending', []],
        ];
        if (! isset($tasks[$this->argument('task')])) {
            $this->error('Unknown company task.');

            return self::FAILURE;
        }

        return app(CompanyContext::class)->run($company->id, function () use ($tasks): int {
            if ($this->option('due') && ! AutomationSchedule::isDueNow($this->option('due'))) {
                return self::SUCCESS;
            }
            [$command, $arguments] = $tasks[$this->argument('task')];
            $status = Artisan::call($command, $arguments);
            $this->output->write(Artisan::output());

            return $status;
        });
    }
}
