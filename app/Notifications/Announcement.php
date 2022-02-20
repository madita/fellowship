<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
     * @param mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->greeting($this->message['subject'])
            ->line($this->message['body'])
            ->action($this->message['action'], $this->message['url'])
            ->line($this->message['thanks']);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'subject'  => $this->message['subject'],
            'body'     => $this->message['body'],
            'notifier' => auth()->user(),
            'url'      => $this->message['url'],
            'action'   => $this->message['action'],
            'thanks'   => $this->message['thanks']
        ];
    }
}
