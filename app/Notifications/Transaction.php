<?php

namespace App\Notifications;

use App\Models\Transaction as ModelsTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class Transaction extends Notification implements ShouldQueue
{
    use Queueable;

    private $transaction;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(ModelsTransaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
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
        return (new MailMessage)
            ->greeting("{$notifiable->first_name}")
            ->line('Below are details of a transaction made to your account today.')
            ->line("ID: {$this->transaction->id}")
            ->line("Type: {$this->transaction->type}")
            ->line("Channel: {ucfirst(str_replace('-', ' ',$this->transaction->channel))}")
            ->line("Amount: {$this->transaction->meta->data->currency} {$this->transaction->amount}")
            ->line("Status: {ucfirst($this->transaction->meta->status)}")
            ->line('Thank you for using ' . config('app.name') . '!');
    }

    /**
     * Get the Vonage / SMS representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\VonageMessage
     */
    public function toVonage($notifiable)
    {
        return (new VonageMessage)
            ->clientReference(config('app.name'))
            ->content("{$this->transaction}");
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'user_id' => $notifiable->id,
            'body' => json_encode($this->transaction)
        ];
    }
}
