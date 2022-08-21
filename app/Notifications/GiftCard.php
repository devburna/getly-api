<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GiftCard extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        if ($notifiable->user_id) {
            return ['mail', 'database'];
        }

        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $senderName = $notifiable->sender->first_name . ' ' . $notifiable->sender->last_name;
        $url = "/redeem?gift={$notifiable->id}?receiver_name={$notifiable->receiver_name}?receiver_email_adress={$notifiable->receiver_email_adress}?receiver_phone_number={$notifiable->receiver_phone_number}?sender_name={$senderName}?message={$notifiable->message}";

        return (new MailMessage)
            ->subject("Gift Card From {$notifiable->sender->first_name}")
            ->greeting("Hi, {$notifiable->receiver_name}")
            ->line("You've got a gift from {$notifiable->sender->first_name}, use the button below to redeem your gift.")
            ->action('Redeem Gift', url($url))
            ->line('Thank you for using ' . config('app.name') . '!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        $senderName = $notifiable->sender->first_name . ' ' . $notifiable->sender->last_name;

        return [
            'user_id' => $notifiable->user_id,
            'body' => "You just got a gift from {$senderName}.",
            'action' => 'Check it out now.',
            'link' => url("/dashboard/gifts-cards?gift={$notifiable->id}"),
        ];
    }
}
