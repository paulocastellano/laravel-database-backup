<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PauloCastellano\DatabaseBackup\Console\Commands\DatabaseBackupCommand;
use PauloCastellano\DatabaseBackup\DatabaseBackup;
use PauloCastellano\DatabaseBackup\Exceptions\DatabaseBackupException;
use PauloCastellano\DatabaseBackup\Mail\BackupFailed;
use PauloCastellano\DatabaseBackup\Mail\BackupSucceeded;

beforeEach(function (): void {
    Storage::fake(DatabaseBackup::DISK);
    config()->set('laravel-database-backup.disk.bucket', 'backups');
    config()->set('laravel-database-backup.directory', 'my-app');
    config()->set('laravel-database-backup.connection', 'backup_pgsql');
    config()->set('database.connections.backup_pgsql', ['driver' => 'pgsql', 'host' => 'db.internal', 'port' => '5432', 'database' => 'my_app', 'username' => 'my_app', 'password' => 'secret']);
    config()->set('laravel-database-backup.notifications.email', 'ops@example.com');
    config()->set('laravel-database-backup.notifications.discord_webhook_url', 'https://discord.test/api/webhooks/1/token');
    $this->freezeTime();
});

test('backs up, prunes old backups and announces it by email and on discord', function (): void {
    config()->set('laravel-database-backup.keep_days', 30);
    $old = storeBackupAgedDays('my-app', 31);
    Mail::fake();
    Http::fake();
    Process::fake(['*' => Process::result(output: 'dump')]);
    $path = 'my-app/'.now('UTC')->format('YmdHis').'.sql.gz';

    $this->artisan('backup:database')
        ->expectsOutput("Database backed up to {$path}.")
        ->expectsOutput('1 old backup(s) deleted.')
        ->assertSuccessful();

    expect(Storage::disk(DatabaseBackup::DISK)->allFiles())->toBe([$path])->not->toContain($old);
    Mail::assertSent(BackupSucceeded::class, fn (BackupSucceeded $mail): bool => $mail->hasTo('ops@example.com') && $mail->path === $path);
    Mail::assertNotSent(BackupFailed::class);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://discord.test/api/webhooks/1/token'
        && $request['embeds'][0]['title'] === '✅ Database backup succeeded'
        && $request['embeds'][0]['fields'][0]['value'] === $path
        && $request['embeds'][0]['fields'][3]['value'] === config('app.env')
        && $request['embeds'][0]['fields'][4]['value'] === '<t:'.now()->getTimestamp().':f>');
});

test('a failed backup is announced by email and on discord, and the command still fails', function (): void {
    Mail::fake();
    Http::fake();
    Process::fake(['*' => Process::result(errorOutput: 'connection refused', exitCode: 1)]);

    expect(fn () => $this->artisan('backup:database')->run())->toThrow(DatabaseBackupException::class, 'connection refused');

    Mail::assertSent(BackupFailed::class, fn (BackupFailed $mail): bool => $mail->hasTo('ops@example.com') && str_contains($mail->error, 'connection refused'));
    Mail::assertNotSent(BackupSucceeded::class);
    Http::assertSent(fn (Request $request): bool => $request['embeds'][0]['title'] === '❌ Database backup failed'
        && str_contains((string) $request['embeds'][0]['fields'][2]['value'], 'connection refused'));
});

test('a missing bucket fails before dumping and is announced', function (): void {
    config()->set('laravel-database-backup.disk.bucket', null);
    Mail::fake();
    Http::fake();
    Process::fake();

    expect(fn () => $this->artisan('backup:database')->run())->toThrow(DatabaseBackupException::class, 'BACKUP_BUCKET');

    Process::assertNothingRan();
    Mail::assertSent(BackupFailed::class, fn (BackupFailed $mail): bool => str_contains($mail->error, 'BACKUP_BUCKET'));
});

test('a long error is cut to fit a discord embed field, and sent whole by email', function (): void {
    Mail::fake();
    Http::fake();
    $error = str_repeat('pg_dump: error: ', 200);
    Process::fake(['*' => Process::result(errorOutput: $error, exitCode: 1)]);

    expect(fn () => $this->artisan('backup:database')->run())->toThrow(DatabaseBackupException::class);

    Http::assertSent(fn (Request $request): bool => mb_strlen((string) $request['embeds'][0]['fields'][2]['value']) <= 1024);
    Mail::assertSent(BackupFailed::class, fn (BackupFailed $mail): bool => str_contains($mail->error, trim($error)));
});

test('nothing is announced when no email or discord webhook is set', function (): void {
    config()->set('laravel-database-backup.notifications.email', null);
    config()->set('laravel-database-backup.notifications.discord_webhook_url', null);
    Mail::fake();
    Http::fake();
    Process::fake(['*' => Process::result(output: 'dump')]);

    $this->artisan('backup:database')->assertSuccessful();

    Mail::assertNothingSent();
    Http::assertNothingSent();
});

test('a notification that fails neither fails the backup nor stops the other notification', function (): void {
    config()->set('mail.default', 'missing-mailer');
    Http::fake(['*' => Http::response(status: 500)]);
    Process::fake(['*' => Process::result(output: 'dump')]);

    $this->artisan('backup:database')->assertSuccessful();

    Http::assertSentCount(1);
    expect(Storage::disk(DatabaseBackup::DISK)->allFiles())->toHaveCount(1);
});

test('the schedule runs the backup every configured hours, in UTC, only when backups are enabled', function (bool $enabled, int $hours, string $cron): void {
    config()->set('laravel-database-backup.enabled', $enabled);
    config()->set('laravel-database-backup.interval_hours', $hours);

    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'backup:database'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe($cron)
        ->and($event->timezone)->toBe('UTC')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->filtersPass(app()))->toBe($enabled);
})->with([true, false])->with([
    'the default' => [6, '0 */6 * * *'],
    'every 4 hours' => [4, '0 */4 * * *'],
]);

test('the command is registered as backup:database', function (): void {
    expect(Artisan::all())->toHaveKey('backup:database')
        ->and(Artisan::all()['backup:database'])->toBeInstanceOf(DatabaseBackupCommand::class);
});
