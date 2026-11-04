<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class WorkspaceNotification extends Notification
{
    public function __construct(
        private readonly string $title,
        private readonly string $detail,
        private readonly string $type,
        private readonly string $href,
        private readonly array $meta = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'detail' => $this->detail,
            'type' => $this->type,
            'href' => $this->href,
            'meta' => $this->meta,
        ];
    }
}
