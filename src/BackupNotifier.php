<?php

declare(strict_types=1);

namespace PauloCastellano\DatabaseBackup;

use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use PauloCastellano\DatabaseBackup\Mail\BackupFailed;
use PauloCastellano\DatabaseBackup\Mail\BackupSucceeded;
use Throwable;

class BackupNotifier
{
    private const int SUCCEEDED_COLOR = 0x22C55E;

    private const int FAILED_COLOR = 0xEF4444;

    /**
     * Discord refuses an embed field longer than 1024 characters; the error
     * also needs room for its code block fence.
     */
    private const int DISCORD_ERROR_LIMIT = 1000;

    private const int DISCORD_TIMEOUT_SECONDS = 10;

    public function succeeded(string $path, int $bytes, CarbonInterval $duration): void
    {
        $finishedAt = now();
        $appName = (string) config('app.name');

        $this->send(new BackupSucceeded($path, $bytes, $duration, $finishedAt), [
            'title' => '✅ Database backup succeeded',
            'description' => "A new backup of the {$appName} database is stored in your backup bucket.",
            'color' => self::SUCCEEDED_COLOR,
            'fields' => [
                ['name' => 'File', 'value' => $path, 'inline' => true],
                ['name' => 'Size', 'value' => Number::fileSize($bytes, precision: 1), 'inline' => true],
                ['name' => 'Duration', 'value' => $duration->forHumans(['short' => true]), 'inline' => true],
                ['name' => 'Environment', 'value' => (string) config('app.env'), 'inline' => true],
                ['name' => 'Date', 'value' => $this->discordDate($finishedAt), 'inline' => true],
            ],
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $finishedAt = now();
        $appName = (string) config('app.name');
        $error = Str::limit($exception->getMessage(), self::DISCORD_ERROR_LIMIT);

        $this->send(new BackupFailed($exception->getMessage(), $finishedAt), [
            'title' => '❌ Database backup failed',
            'description' => "The last backup of the {$appName} database did not finish.",
            'color' => self::FAILED_COLOR,
            'fields' => [
                ['name' => 'Environment', 'value' => (string) config('app.env'), 'inline' => true],
                ['name' => 'Date', 'value' => $this->discordDate($finishedAt), 'inline' => true],
                ['name' => 'Error', 'value' => "```\n{$error}\n```", 'inline' => false],
            ],
        ]);
    }

    /**
     * Sends to the email address and the Discord webhook the app set
     * (`BACKUP_NOTIFY_EMAIL`, `BACKUP_NOTIFY_DISCORD_WEBHOOK_URL`); one left
     * blank is skipped. Each goes on its own: a notification that fails is
     * reported and never hides the backup's own result or stops the other.
     *
     * @param  array<string, mixed>  $discordEmbed
     */
    private function send(Mailable $mailable, array $discordEmbed): void
    {
        $email = config('laravel-database-backup.notifications.email');
        $discordWebhookUrl = config('laravel-database-backup.notifications.discord_webhook_url');

        if (filled($email)) {
            rescue(fn () => Mail::to((string) $email)->send($mailable));
        }

        if (filled($discordWebhookUrl)) {
            rescue(fn () => Http::timeout(self::DISCORD_TIMEOUT_SECONDS)
                ->post((string) $discordWebhookUrl, ['embeds' => [$discordEmbed]])
                ->throw());
        }
    }

    /**
     * Discord draws `<t:…:f>` as the date and time in each reader's own zone
     * and language.
     */
    private function discordDate(CarbonInterface $at): string
    {
        $timestamp = $at->getTimestamp();

        return "<t:{$timestamp}:f>";
    }
}
