<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use PauloCastellano\DatabaseBackup\DatabaseBackup;

test('merges the package config and registers its private bucket as a disk', function (): void {
    expect(config('laravel-database-backup.enabled'))->toBeFalse()
        ->and(config('laravel-database-backup.interval_hours'))->toBe(6)
        ->and(config('laravel-database-backup.keep_days'))->toBe(30)
        ->and(config('laravel-database-backup.timeout'))->toBe(3600)
        ->and(config('filesystems.disks.'.DatabaseBackup::DISK))->toMatchArray(['driver' => 's3', 'visibility' => 'private', 'throw' => true]);
});

test('publishes the config', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'laravel-database-backup-config', '--force' => true])->assertSuccessful();

    expect(config_path('laravel-database-backup.php'))->toBeFile();
})->after(fn () => File::delete(config_path('laravel-database-backup.php')));
