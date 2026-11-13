<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use PauloCastellano\DatabaseBackup\DatabaseBackup;
use PauloCastellano\DatabaseBackup\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Stores a backup in the faked bucket, `$days` old.
 */
function storeBackupAgedDays(string $directory, int $days): string
{
    $filename = now('UTC')->subDays($days)->format('YmdHis');
    $path = "{$directory}/{$filename}.sql.gz";
    Storage::disk(DatabaseBackup::DISK)->put($path, 'dump');
    touch(Storage::disk(DatabaseBackup::DISK)->path($path), now()->subDays($days)->getTimestamp());

    return $path;
}
