<?php

declare(strict_types=1);

namespace PauloCastellano\DatabaseBackup\Mail;

use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent at once, never queued: the failure is heard about even when the queue
 * workers are down.
 */
class BackupFailed extends Mailable
{
    public function __construct(
        public string $error,
        public CarbonInterface $finishedAt,
    ) {}

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name');

        return new Envelope(subject: "Database backup failed: {$appName}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'laravel-database-backup::mail.failed',
            with: [
                'appName' => config('app.name'),
                'environment' => config('app.env'),
                'error' => $this->error,
                'date' => "{$this->finishedAt->copy()->utc()->isoFormat('LL HH:mm')} UTC",
            ],
        );
    }
}
