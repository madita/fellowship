<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class Announcement extends Notification
{
    use Queueable;

    private $message;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
            ->greeting($this->message['subject'])
            ->line($this->message['body']);

        // A button only when the announcement carries one
        if ( ! empty($this->message['action']) && ! empty($this->message['url'])) {
            $mail->action($this->message['action'], $this->message['url']);
        }

        return $mail->line($this->message['thanks'] ?? '');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        // Only subject, body and thanks are required of an announcement
        return [
            'subject'  => $this->message['subject'],
            'body'     => $this->message['body'],
            'notifier' => auth()->user(),
            'url'      => $this->message['url'] ?? null,
            'action'   => $this->message['action'] ?? null,
            'thanks'   => $this->message['thanks'] ?? null,
        ];
    }
}
