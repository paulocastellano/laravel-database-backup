<?php

declare(strict_types=1);

namespace PauloCastellano\DatabaseBackup\Console\Commands;

use Illuminate\Console\Command;
use PauloCastellano\DatabaseBackup\BackupNotifier;
use PauloCastellano\DatabaseBackup\DatabaseBackup;
use Throwable;

class DatabaseBackupCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'backup:database';

    /**
     * The command description.
     */
    protected $description = 'Dump the database, store it gzipped in the backup bucket, delete backups past their retention and notify the result';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseBackup $databaseBackup, BackupNotifier $notifier): int
    {
        $startedAt = now();

        try {
            $backup = $databaseBackup->backup();
            $deleted = $databaseBackup->prune(now());
        } catch (Throwable $exception) {
            $notifier->failed($exception);

            throw $exception;
        }

        $notifier->succeeded($backup['path'], $backup['bytes'], $startedAt->diffAsCarbonInterval(now())->cascade());

        $this->info("Database backed up to {$backup['path']}.");
        $this->info("{$deleted} old backup(s) deleted.");

        return self::SUCCESS;
    }
}
