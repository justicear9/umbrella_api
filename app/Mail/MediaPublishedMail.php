<?php

namespace App\Mail;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MediaPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public MediaAsset $asset) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New media: '.$this->asset->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.media-published',
            with: [
                'user' => $this->user,
                'asset' => $this->asset,
            ],
        );
    }
}
