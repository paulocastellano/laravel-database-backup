<?php

declare(strict_types=1);

namespace PauloCastellano\DatabaseBackup\Mail;

use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Number;

/**
 * Sent at once, never queued: the backup is heard about even when the queue
 * workers are down.
 */
class BackupSucceeded extends Mailable
{
    public function __construct(
        public string $path,
        public int $bytes,
        public CarbonInterval $duration,
        public CarbonInterface $finishedAt,
    ) {}

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name');

        return new Envelope(subject: "Database backup succeeded: {$appName}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'laravel-database-backup::mail.succeeded',
            with: [
                'appName' => config('app.name'),
                'environment' => config('app.env'),
                'path' => $this->path,
                'size' => Number::fileSize($this->bytes, precision: 1),
                'duration' => $this->duration->forHumans(['short' => true]),
                'date' => "{$this->finishedAt->copy()->utc()->isoFormat('LL HH:mm')} UTC",
            ],
        );
    }
}
