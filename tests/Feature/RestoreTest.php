<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PauloCastellano\DatabaseBackup\DatabaseBackup;

/*
 * Runs the real pg_dump or mysqldump against a scratch database on the
 * suite's own server (DB_CONNECTION=pgsql or mysql), then restores the stored
 * backup into an empty database with psql or mysql: a backup that cannot be
 * restored is worth nothing. Skipped on SQLite, the Testbench default.
 */
beforeEach(function (): void {
    $default = Arr::except(DB::connection()->getConfig(), 'name');

    if (! in_array($default['driver'], ['pgsql', 'mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Needs a PostgreSQL, MySQL or MariaDB server: set DB_CONNECTION and the DB_* variables.');
    }

    $this->scratchDatabase = "{$default['database']}_backup_check";

    config()->set('database.connections.backup_admin', $default);
    config()->set('database.connections.backup_check', [...$default, 'database' => $this->scratchDatabase]);
    config()->set('laravel-database-backup.disk.bucket', 'backups');
    config()->set('laravel-database-backup.directory', 'my-app');

    recreateScratchDatabase($this->scratchDatabase);
    Storage::fake(DatabaseBackup::DISK);
});

afterEach(function (): void {
    if (! isset($this->scratchDatabase)) {
        return;
    }

    DB::purge('backup_check');
    Schema::connection('backup_admin')->dropDatabaseIfExists($this->scratchDatabase);
    DB::purge('backup_admin');
});

test('a backup restores into an empty database with every table and row as they were', function (): void {
    $schema = Schema::connection('backup_check');

    $schema->create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('email')->unique();
        $table->string('name');
        $table->timestamps();
    });

    $schema->create('posts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->longText('body')->nullable();
        $table->json('meta')->nullable();
        $table->decimal('amount', 12, 4)->nullable();
        $table->boolean('published')->default(false);
        $table->timestamp('published_at')->nullable()->index();
    });

    $connection = DB::connection('backup_check');
    $connection->table('users')->insert([
        ['email' => 'ada@example.com', 'name' => 'Ada', 'created_at' => now()->startOfSecond(), 'updated_at' => now()->startOfSecond()],
        ['email' => 'grace@example.com', 'name' => "Grace O'Neil", 'created_at' => null, 'updated_at' => null],
    ]);
    $connection->table('posts')->insert([
        [
            'user_id' => 1,
            'body' => "It's a \"quoted\" line\nwith a tab\tand a backslash \\ and \\N",
            'meta' => json_encode(['emoji' => '🐧', 'rtl' => 'مرحبا', 'nested' => ['list' => [1, 2]]]),
            'amount' => '1234.5678',
            'published' => true,
            'published_at' => now()->startOfSecond(),
        ],
        ['user_id' => 1, 'body' => '', 'meta' => null, 'amount' => null, 'published' => false, 'published_at' => null],
        ['user_id' => 2, 'body' => "COPY posts FROM stdin;\n\\.\nDROP TABLE users;", 'meta' => '[]', 'amount' => '-0.0001', 'published' => false, 'published_at' => null],
        ['user_id' => 2, 'body' => str_repeat('Laravel ', 20000), 'meta' => '{}', 'amount' => '0', 'published' => true, 'published_at' => now()->subYear()->startOfSecond()],
    ]);

    $before = scratchDatabaseSnapshot();

    ['path' => $path] = app(DatabaseBackup::class)->backup('backup_check');

    recreateScratchDatabase($this->scratchDatabase);
    expect(scratchDatabaseSnapshot())->toBeEmpty();

    restoreScratchDatabase((string) gzdecode((string) Storage::disk(DatabaseBackup::DISK)->get($path)));

    expect(scratchDatabaseSnapshot())->toEqual($before)
        ->and($before)->toHaveKeys(['users', 'posts'])
        ->and($before['posts'])->toHaveCount(4);
});

function recreateScratchDatabase(string $database): void
{
    DB::purge('backup_check');
    Schema::connection('backup_admin')->dropDatabaseIfExists($database);
    Schema::connection('backup_admin')->createDatabase($database);
}

/**
 * Restores a plain SQL dump the way an operator would, failing on the first
 * error.
 */
function restoreScratchDatabase(string $dump): void
{
    DB::purge('backup_check');
    $config = fn (string $key): string => (string) config("database.connections.backup_check.{$key}");

    [$command, $environment] = match ($config('driver')) {
        'pgsql' => [
            ['psql', '--quiet', '--set=ON_ERROR_STOP=1', "--host={$config('host')}", "--port={$config('port')}", "--username={$config('username')}", "--dbname={$config('database')}"],
            ['PGPASSWORD' => $config('password')],
        ],
        default => [
            ['mysql', "--host={$config('host')}", "--port={$config('port')}", "--user={$config('username')}", $config('database')],
            ['MYSQL_PWD' => $config('password')],
        ],
    };

    Process::env($environment)->input($dump)->timeout(120)->run($command)->throw();
}

/**
 * Every table of the scratch database with its rows, sorted so the order
 * the engine returns them in never matters.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function scratchDatabaseSnapshot(): array
{
    $connection = DB::connection('backup_check');
    $schema = Schema::connection('backup_check');

    return collect($schema->getTableListing($schema->getCurrentSchemaName(), schemaQualified: false))
        ->sort()
        ->values()
        ->mapWithKeys(fn (string $table): array => [$table => $connection->table($table)->get()
            ->map(fn (object $row): array => (array) $row)
            ->sortBy(fn (array $row): string => (string) json_encode($row))
            ->values()
            ->all()])
        ->all();
}
