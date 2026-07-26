<?php

namespace App\Mail;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NoticePublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Notice $notice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->notice->priority === 'urgent' ? '[Urgent] ' : '').$this->notice->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notice-published',
            with: [
                'user' => $this->user,
                'notice' => $this->notice,
            ],
        );
    }
}
