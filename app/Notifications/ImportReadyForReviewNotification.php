<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

final class ImportReadyForReviewNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $importType,
        private readonly string $filename,
        private readonly string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => __('Import ready for review'),
            'message' => __('Review :file before the import is approved.', ['file' => $this->filename]),
            'import_type' => $this->importType,
            'filename' => $this->filename,
            'url' => $this->url,
        ]);
    }
}
