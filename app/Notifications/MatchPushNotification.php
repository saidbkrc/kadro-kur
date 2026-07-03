<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Genel web push bildirimi: başlık + metin + tıklanınca açılacak URL.
 * Tüm maç olayları (yeni maç, kadro oylaması, skor, hatırlatma) bunu kullanır.
 */
class MatchPushNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public ?string $tag = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $message = (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/icon-192.png')
            ->badge('/icon-192.png')
            ->data(['url' => $this->url]);

        if ($this->tag !== null) {
            $message->tag($this->tag); // aynı tag'li eski bildirim yenisiyle değişir
        }

        return $message;
    }
}
