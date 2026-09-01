<?php

namespace App\Mail;

use App\Models\NotificationCampaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificationCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NotificationCampaign $campaign,
        public User $user
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->campaign->subject ?: $this->campaign->title)
            ->view('emails.notification-campaign');
    }
}
