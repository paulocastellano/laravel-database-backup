<?php

declare(strict_types=1);

namespace PauloCastellano\DatabaseBackup;

use Carbon\CarbonInterface;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\StorageAttributes;
use RuntimeException;
use Symfony\Component\Process\Process as SymfonyProcess;

class DatabaseBackup
{
    /**
     * The filesystem disk the package registers from its `disk` config.
     */
    public const string DISK = 'laravel-database-backup';

    private const int TIMEOUT_SECONDS = 3600;

    /**
     * Dumps the connection with the engine's own tool, gzips the output while
     * it streams (the dump never sits in memory) and stores it in the backup
     * directory of the bucket. The password goes through the environment,
     * never the command line.
     *
     * @return array{path: string, bytes: int} where the backup is in the bucket and its size
     */
    public function backup(?string $connectionName = null): array
    {
        if (blank(config('laravel-database-backup.disk.bucket'))) {
            throw new RuntimeException('Set BACKUP_BUCKET to the bucket the backups go to.');
        }

        $directory = $this->directory();
        $connection = DB::connection($connectionName ?? config('laravel-database-backup.connection'));
        [$command, $environment] = $this->dumpCommand($connection);

        $random = Str::random(40);
        $temporaryPath = sys_get_temp_dir()."/database-backup-{$random}.sql.gz";
        $gzip = gzopen($temporaryPath, 'wb6');
        $errorOutput = '';

        if ($gzip === false) {
            throw new RuntimeException("Could not open {$temporaryPath} to write the backup.");
        }

        try {
            try {
                $result = Process::env($environment)
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->quietly()
                    ->start($command, function (string $type, string $output) use ($gzip, &$errorOutput): void {
                        if ($type === SymfonyProcess::OUT) {
                            gzwrite($gzip, $output);
                        } else {
                            $errorOutput .= $output;
                        }
                    })
                    ->wait();
            } finally {
                gzclose($gzip);
            }

            if ($result->failed()) {
                $errors = Str::trim($errorOutput);

                throw new RuntimeException("{$command[0]} failed with exit code {$result->exitCode()}: {$errors}");
            }

            $filename = now('UTC')->format('YmdHis');
            $path = (string) Storage::disk(self::DISK)->putFileAs($directory, $temporaryPath, "{$filename}.sql.gz");

            return ['path' => $path, 'bytes' => File::size($temporaryPath)];
        } finally {
            File::delete($temporaryPath);
        }
    }

    /**
     * Deletes the backups older than `keep_days` (0 keeps them all). Ages
     * come from the directory listing, so the bucket is not asked once per
     * file. Only the backup directory is ever listed, never the rest of the
     * bucket.
     *
     * @return int the number of backups deleted
     */
    public function prune(CarbonInterface $now): int
    {
        $keepDays = (int) config('laravel-database-backup.keep_days');

        if ($keepDays < 1) {
            return 0;
        }

        $disk = Storage::disk(self::DISK);
        $cutoff = $now->copy()->subDays($keepDays)->getTimestamp();

        /** @var list<string> $expired */
        $expired = collect($disk->listContents($this->directory(), false)->toArray())
            ->filter(fn (StorageAttributes $entry): bool => $entry->isFile()
                && filled($entry->lastModified())
                && $entry->lastModified() < $cutoff)
            ->map(fn (StorageAttributes $entry): string => $entry->path())
            ->values()
            ->all();

        $disk->delete($expired);

        return count($expired);
    }

    /**
     * The directory of the bucket the backups go to (`BACKUP_DIRECTORY`), so
     * one bucket can hold the backups of several apps. It is required and is
     * never the root: the directory belongs to the backups alone, so pruning
     * may empty it.
     */
    public function directory(): string
    {
        $directory = trim((string) config('laravel-database-backup.directory'), '/');

        if (blank($directory)) {
            throw new RuntimeException('Set BACKUP_DIRECTORY to the directory of the bucket the backups go to.');
        }

        return $directory;
    }

    /**
     * Every `$hours` hours on the hour, clamped to 1–24.
     */
    public static function cron(int $hours): string
    {
        $hours = max(1, min(24, $hours));

        return "0 */{$hours} * * *";
    }

    /**
     * @return array{0: list<string>, 1: array<string, string>}
     */
    private function dumpCommand(Connection $connection): array
    {
        $config = fn (string $key): string => (string) $connection->getConfig($key);

        return match ($connection->getDriverName()) {
            'pgsql' => [
                [
                    'pg_dump',
                    '--no-owner',
                    '--no-privileges',
                    "--host={$config('host')}",
                    "--port={$config('port')}",
                    "--username={$config('username')}",
                    "--dbname={$config('database')}",
                ],
                ['PGPASSWORD' => $config('password'), 'PGSSLMODE' => filled($config('sslmode')) ? $config('sslmode') : 'prefer'],
            ],
            'mysql', 'mariadb' => [
                [
                    'mysqldump',
                    '--single-transaction',
                    '--routines',
                    '--triggers',
                    '--no-tablespaces',
                    '--default-character-set=utf8mb4',
                    ...(filled($config('unix_socket'))
                        ? ["--socket={$config('unix_socket')}"]
                        : ["--host={$config('host')}", "--port={$config('port')}"]),
                    "--user={$config('username')}",
                    $config('database'),
                ],
                ['MYSQL_PWD' => $config('password')],
            ],
            default => throw new RuntimeException("Backups support PostgreSQL, MySQL and MariaDB, not {$connection->getDriverName()}."),
        };
    }
}
