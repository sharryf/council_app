<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email side of an Inventory alert — one generic class for every
 * event this phase wires (spec 13), rather than a class per event
 * type. The in-app/bell side is handled separately by
 * InventoryNotifier via Filament's own Notification::sendToDatabase()
 * (that's what actually renders in the panel's bell icon; this class's
 * own toDatabase() would just be a second, differently-shaped row
 * Filament's bell doesn't know how to render), so via() only ever
 * returns ['mail'] — InventoryNotifier decides whether to dispatch
 * this at all based on email_notifications_enabled.
 */
class InventoryAlert extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly ?string $body = null,
        private readonly ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title)->line($this->title);

        if (filled($this->body)) {
            $mail->line($this->body);
        }

        if (filled($this->url)) {
            $mail->action('View', $this->url);
        }

        return $mail;
    }
}
