<?php

namespace App\Mail;

use App\Models\PressPrepSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PressPrepAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $communicator,
        public PressPrepSession $session,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Press Prep assigned — NDC Communicators',
        );
    }

    public function content(): Content
    {
        $outing = config('ndc.outing_types.'.$this->session->outing_type, $this->session->outing_type);
        $difficulty = config('ndc.difficulties.'.$this->session->difficulty, $this->session->difficulty);
        $topics = is_array($this->session->topics) ? implode(', ', $this->session->topics) : '';

        return new Content(
            text: 'mail.press-prep-assigned',
            with: [
                'name' => $this->communicator->name,
                'outing' => $outing,
                'difficulty' => $difficulty,
                'mode' => $this->session->interview_mode,
                'topics' => $topics,
                'note' => $this->session->assignment_note,
                'questionCount' => $this->session->question_count,
            ],
        );
    }
}
