<?php

declare(strict_types=1);

namespace PauloCastellano\DatabaseBackup;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use PauloCastellano\DatabaseBackup\Console\Commands\DatabaseBackupCommand;

class DatabaseBackupServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-database-backup.php', 'laravel-database-backup');

        $this->app->singleton(DatabaseBackup::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config(['filesystems.disks.'.DatabaseBackup::DISK => config('laravel-database-backup.disk')]);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-database-backup');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laravel-database-backup.php' => config_path('laravel-database-backup.php'),
        ], ['laravel-database-backup', 'laravel-database-backup-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-database-backup'),
        ], ['laravel-database-backup', 'laravel-database-backup-views']);

        $this->commands([
            DatabaseBackupCommand::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(DatabaseBackupCommand::class)
                ->cron(DatabaseBackup::cron((int) config('laravel-database-backup.interval_hours')))
                ->timezone('UTC')
                ->when(fn (): bool => (bool) config('laravel-database-backup.enabled'))
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
