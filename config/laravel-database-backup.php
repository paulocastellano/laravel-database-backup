<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | When enabled, the scheduler runs `php artisan backup:database` every
    | `interval_hours` hours on the hour, in UTC (1 to 24). The command
    | always runs when called by hand.
    |
    */

    'enabled' => (bool) env('BACKUP_ENABLED', false),

    'interval_hours' => (int) env('BACKUP_INTERVAL_HOURS', 6),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Backups older than this many days are deleted from `directory` after
    | each run. 0 keeps every backup.
    |
    */

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | The connection to dump: PostgreSQL (pg_dump), MySQL or MariaDB
    | (mysqldump). Null dumps the default connection.
    |
    */

    'connection' => env('BACKUP_DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Bucket
    |--------------------------------------------------------------------------
    |
    | Any S3-compatible bucket: Amazon S3, Cloudflare R2, DigitalOcean
    | Spaces, MinIO... `endpoint` picks the provider. Keep the bucket
    | private. The bucket and `directory` are required: the directory
    | belongs to the backups alone, so pruning may empty it, and one bucket
    | can hold the backups of several apps, each in its own directory.
    |
    */

    'directory' => env('BACKUP_DIRECTORY'),

    'disk' => [
        'driver' => 's3',
        'key' => env('BACKUP_ACCESS_KEY_ID'),
        'secret' => env('BACKUP_SECRET_ACCESS_KEY'),
        'region' => env('BACKUP_REGION', 'auto'),
        'bucket' => env('BACKUP_BUCKET'),
        'endpoint' => env('BACKUP_ENDPOINT'),
        'use_path_style_endpoint' => true,
        'visibility' => 'private',
        'throw' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Every run, successful or not, is sent to this email address and to
    | this Discord webhook. Leave either blank to skip it.
    |
    */

    'notifications' => [
        'email' => env('BACKUP_NOTIFY_EMAIL'),
        'discord_webhook_url' => env('BACKUP_NOTIFY_DISCORD_WEBHOOK_URL'),
    ],

];
