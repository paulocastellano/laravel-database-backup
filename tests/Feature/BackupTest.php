<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;
use PauloCastellano\DatabaseBackup\DatabaseBackup;
use PauloCastellano\DatabaseBackup\Exceptions\DatabaseBackupException;

beforeEach(function (): void {
    Storage::fake(DatabaseBackup::DISK);
    config()->set('laravel-database-backup.disk.bucket', 'backups');
    config()->set('laravel-database-backup.directory', 'my-app');
    config()->set('database.connections.backup_pgsql', [
        'driver' => 'pgsql',
        'host' => 'db.internal',
        'port' => '5432',
        'database' => 'my_app',
        'username' => 'my_app',
        'password' => 'secret',
    ]);
    $this->freezeTime();
});

test('dumps postgresql with pg_dump and stores the gzipped dump in the backup directory', function (array $ssl, string $sslMode): void {
    config()->set('database.connections.backup_pgsql', [...config('database.connections.backup_pgsql'), ...$ssl]);
    Process::fake(['*pg_dump*' => Process::result(output: "CREATE TABLE posts ();\n")]);

    ['path' => $path, 'bytes' => $bytes] = app(DatabaseBackup::class)->backup('backup_pgsql');

    expect($path)->toBe('my-app/'.now('UTC')->format('YmdHis').'.sql.gz')
        ->and(gzdecode((string) Storage::disk(DatabaseBackup::DISK)->get($path)))->toBe("CREATE TABLE posts ();\n")
        ->and($bytes)->toBe(Storage::disk(DatabaseBackup::DISK)->size($path));

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        'pg_dump',
        '--no-owner',
        '--no-privileges',
        '--host=db.internal',
        '--port=5432',
        '--username=my_app',
        '--dbname=my_app',
    ] && $process->environment === ['PGPASSWORD' => 'secret', 'PGSSLMODE' => $sslMode]);
})->with([
    'with the connection ssl mode' => [['sslmode' => 'require'], 'require'],
    'without one' => [[], 'prefer'],
]);

test('dumps mysql and mariadb in a single transaction with mysqldump and keeps the password off the command line', function (string $driver, array $connection, array $location): void {
    config()->set('database.connections.backup_mysql', [
        'driver' => $driver,
        'database' => 'my_app',
        'username' => 'my_app',
        'password' => 'secret',
        ...$connection,
    ]);
    Process::fake(['*mysqldump*' => Process::result(output: "CREATE TABLE posts ();\n")]);

    ['path' => $path] = app(DatabaseBackup::class)->backup('backup_mysql');

    expect(gzdecode((string) Storage::disk(DatabaseBackup::DISK)->get($path)))->toBe("CREATE TABLE posts ();\n");

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        'mysqldump',
        '--single-transaction',
        '--routines',
        '--triggers',
        '--no-tablespaces',
        '--default-character-set=utf8mb4',
        ...$location,
        '--user=my_app',
        'my_app',
    ] && $process->environment === ['MYSQL_PWD' => 'secret']);
})->with(['mysql', 'mariadb'])->with([
    'over tcp' => [['host' => 'db.internal', 'port' => '3306', 'unix_socket' => ''], ['--host=db.internal', '--port=3306']],
    'over a socket' => [['host' => 'db.internal', 'port' => '3306', 'unix_socket' => '/run/mysqld.sock'], ['--socket=/run/mysqld.sock']],
]);

test('dumps the connection set in the config, else the default one', function (): void {
    config()->set('laravel-database-backup.connection', 'backup_pgsql');
    Process::fake(['*' => Process::result(output: 'dump')]);

    app(DatabaseBackup::class)->backup();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'pg_dump');
});

test('a failed dump throws with the tool error, stores nothing and leaves no temporary file', function (): void {
    $temporaryFiles = temporaryBackupFiles();
    Process::fake(['*' => Process::result(errorOutput: 'connection refused', exitCode: 1)]);

    expect(fn () => app(DatabaseBackup::class)->backup('backup_pgsql'))->toThrow(DatabaseBackupException::class, 'connection refused');

    expect(Storage::disk(DatabaseBackup::DISK)->allFiles())->toBeEmpty()
        ->and(temporaryBackupFiles())->toBe($temporaryFiles);
});

test('the dump is written to a temporary file only its owner can read', function (): void {
    $permissions = null;
    Process::fake(function () use (&$permissions) {
        $permissions = fileperms((string) collect(temporaryBackupFiles())->last()) & 0777;

        return Process::result(output: 'dump');
    });

    app(DatabaseBackup::class)->backup('backup_pgsql');

    expect($permissions)->toBe(0600);
})->skipOnWindows();

test('stops a dump that takes longer than the configured timeout', function (): void {
    config()->set('laravel-database-backup.timeout', 7200);
    Process::fake(['*' => Process::result(output: 'dump')]);

    app(DatabaseBackup::class)->backup('backup_pgsql');

    Process::assertRan(fn (PendingProcess $process): bool => $process->timeout === 7200);
});

test('a failed upload throws and leaves no temporary file', function (): void {
    Storage::fake(DatabaseBackup::DISK, ['throw' => true]);
    Storage::disk(DatabaseBackup::DISK)->makeDirectory('my-app/'.now('UTC')->format('YmdHis').'.sql.gz');
    $temporaryFiles = temporaryBackupFiles();
    Process::fake(['*' => Process::result(output: 'dump')]);

    expect(fn () => app(DatabaseBackup::class)->backup('backup_pgsql'))->toThrow(UnableToWriteFile::class);

    expect(temporaryBackupFiles())->toBe($temporaryFiles);
});

test('refuses a database engine it cannot dump', function (): void {
    config()->set('database.connections.backup_sqlite', ['driver' => 'sqlite', 'database' => ':memory:']);
    Process::fake();

    expect(fn () => app(DatabaseBackup::class)->backup('backup_sqlite'))->toThrow(DatabaseBackupException::class, 'PostgreSQL, MySQL and MariaDB');

    Process::assertNothingRan();
});

test('refuses to back up without a bucket or a directory, before dumping anything', function (string $setting, ?string $value, string $variable): void {
    config()->set($setting, $value);
    Process::fake();

    expect(fn () => app(DatabaseBackup::class)->backup('backup_pgsql'))->toThrow(DatabaseBackupException::class, $variable);

    Process::assertNothingRan();
})->with([
    'no bucket' => ['laravel-database-backup.disk.bucket', null, 'BACKUP_BUCKET'],
    'no directory' => ['laravel-database-backup.directory', null, 'BACKUP_DIRECTORY'],
    'a directory of slashes' => ['laravel-database-backup.directory', '/', 'BACKUP_DIRECTORY'],
]);

test('stores backups in the configured directory, trimmed of slashes', function (): void {
    config()->set('laravel-database-backup.directory', '/backups/my-app/');
    Process::fake(['*' => Process::result(output: 'dump')]);

    ['path' => $path] = app(DatabaseBackup::class)->backup('backup_pgsql');

    expect($path)->toBe('backups/my-app/'.now('UTC')->format('YmdHis').'.sql.gz');
});

test('deletes backups past the retention and leaves the other directories of the bucket alone', function (): void {
    config()->set('laravel-database-backup.keep_days', 30);
    $old = storeBackupAgedDays('my-app', 31);
    $recent = storeBackupAgedDays('my-app', 29);
    $otherApp = storeBackupAgedDays('another-app', 31);

    expect(app(DatabaseBackup::class)->prune(now()))->toBe(1);

    expect(Storage::disk(DatabaseBackup::DISK)->allFiles())
        ->toEqualCanonicalizing([$recent, $otherApp])
        ->not->toContain($old);
});

test('a retention of zero keeps every backup', function (): void {
    config()->set('laravel-database-backup.keep_days', 0);
    $old = storeBackupAgedDays('my-app', 365);

    expect(app(DatabaseBackup::class)->prune(now()))->toBe(0);

    expect(Storage::disk(DatabaseBackup::DISK)->exists($old))->toBeTrue();
});

test('pruning refuses to run without a directory, so it never lists the root of the bucket', function (): void {
    config()->set('laravel-database-backup.directory', '');
    config()->set('laravel-database-backup.keep_days', 30);
    Storage::disk(DatabaseBackup::DISK)->put('notes.txt', 'another app');
    touch(Storage::disk(DatabaseBackup::DISK)->path('notes.txt'), now()->subDays(31)->getTimestamp());

    expect(fn () => app(DatabaseBackup::class)->prune(now()))->toThrow(DatabaseBackupException::class, 'BACKUP_DIRECTORY');

    expect(Storage::disk(DatabaseBackup::DISK)->exists('notes.txt'))->toBeTrue();
});

test('the schedule interval is clamped to every 1 to 24 hours', function (int $hours, string $cron): void {
    expect(DatabaseBackup::cron($hours))->toBe($cron);
})->with([
    [6, '0 */6 * * *'],
    [0, '0 */1 * * *'],
    [48, '0 */24 * * *'],
]);

/**
 * @return list<string>
 */
function temporaryBackupFiles(): array
{
    return glob(sys_get_temp_dir().'/database-backup-*') ?: [];
}
