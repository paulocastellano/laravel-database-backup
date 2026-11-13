<?php

declare(strict_types=1);

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use PauloCastellano\DatabaseBackup\Mail\BackupFailed;
use PauloCastellano\DatabaseBackup\Mail\BackupSucceeded;

test('the success email names the app and shows the file, size, duration, environment and date', function (): void {
    config()->set('app.name', 'My App');
    $finishedAt = now();
    $mail = new BackupSucceeded('my-app/20261113132204.sql.gz', 20895, CarbonInterval::seconds(50), $finishedAt);

    $mail->assertHasSubject('Database backup succeeded: My App');
    $mail->assertSeeInHtml('A new backup of the My App database is stored in your backup bucket.');
    $mail->assertSeeInOrderInHtml(['File', 'my-app/20261113132204.sql.gz', 'Size', Number::fileSize(20895, precision: 1), 'Duration', 'Environment', config('app.env'), 'Date', $finishedAt->copy()->utc()->isoFormat('LL HH:mm').' UTC']);
});

test('the failure email shows the environment, the date and the error, escaped', function (): void {
    config()->set('app.name', 'My App');
    $mail = new BackupFailed('pg_dump failed: <server> refused', now());

    $mail->assertHasSubject('Database backup failed: My App');
    $mail->assertSeeInOrderInHtml(['Environment', 'Date', 'Error']);
    $mail->assertSeeInHtml('pg_dump failed: &lt;server&gt; refused', false);
    $mail->assertDontSeeInHtml('<server>', false);
});

test('the emails can be overridden by the app', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'laravel-database-backup-views', '--force' => true])->assertSuccessful();

    expect(resource_path('views/vendor/laravel-database-backup/mail/succeeded.blade.php'))->toBeFile()
        ->and(resource_path('views/vendor/laravel-database-backup/mail/failed.blade.php'))->toBeFile();
})->after(fn () => File::deleteDirectory(resource_path('views/vendor/laravel-database-backup')));
