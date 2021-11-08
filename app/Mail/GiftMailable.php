<?php

namespace App\Mail;

use App\Models\Gift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GiftMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $gift, $template, $subject;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Gift $gift, $template, $subject)
    {
        $this->gift = $gift;
        $this->template = $template;
        $this->subject = $subject;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.gift.' . $this->template)->subject($this->subject)->with('gift', $this->gift);
    }
}
