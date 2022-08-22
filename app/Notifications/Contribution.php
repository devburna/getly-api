<?php

namespace App\Notifications;

use App\Enums\GetlistItemContributionType;
use App\Models\GetlistItem;
use App\Models\GetlistItemContributor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class Contribution extends Notification implements ShouldQueue
{
    use Queueable;

    private $getlistItem, $getlistItemContributor, $subject;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(GetlistItem $getlistItem, GetlistItemContributor $getlistItemContributor)
    {
        $this->getlistItem = $getlistItem;
        $this->getlistItemContributor = $getlistItemContributor;

        // set subject
        if ($this->getlistItemContributor->type->is(GetlistItemContributionType::BUY())) {
            $this->subject = "{$this->getlistItemContributor->full_name} Bought Your Gift 😇";
        } else {
            $this->subject = "{$this->getlistItemContributor->full_name} Contributed To Your Gift 😇";
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
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
            ->subject($this->subject)
            ->greeting("Hi, {$notifiable->first_name}")
            ->line("{$this->getlistItemContributor->full_name} contributed to your gift.")
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
            ->content($this->subject)
            ->unicode();
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
            'user_id' =>  $notifiable->id,
            'body' =>  $this->subject,
        ];
    }
}
