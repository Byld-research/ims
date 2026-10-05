<?php

namespace App\Mail;

use App\Services\Digest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one daily email (SPEC 5.9). Sent only when the digest has something to report.
 */
class DailyDigest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Digest $digest, public readonly string $date) {}

    public function envelope(): Envelope
    {
        $scope = $this->digest->site?->code ?? __('All sites');

        return new Envelope(subject: __('BPC Inventory · :scope · :summary', ['scope' => $scope, 'summary' => $this->digest->summary()]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.daily-digest');
    }
}
