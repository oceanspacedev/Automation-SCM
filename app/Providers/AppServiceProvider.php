<?php

namespace App\Providers;

use App\Services\AreaContext;
use App\Services\BrandContext;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AreaContext::class);
        $this->app->scoped(BrandContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // `composer run dev` / `php artisan dev` also runs the scheduler, so Jadwal Otomatis works locally
        DevCommands::artisan('schedule:work', 'scheduler');
    }
}
