<?php

namespace Pipeandpiper\Mail\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericMailable extends Mailable
{
    use Queueable, SerializesModels;

    protected static string $emailView = '';

    private array $emailReplacements;

    public const NAME = "Mailable";

    /**
     * The variable names expected of the mailable view bag.
     * No other variable names will be accepted by the API endpoint and all must be included.
     */
    public const VARIABLES = [];

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $toAddress, array $replacements = [])
    {
        $this->emailReplacements = $replacements;
        $this->setAddress($toAddress);
    }

    /**
     * Get the message envelope.
     *
     * @return Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Generic Mailable',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content(): Content
    {
        return (new Content(
            view: static::$emailView,
            with: $this->emailReplacements,
        ))->with('subject', $this->subject);
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Returns the mailable view name, used for the API endpoint template slugs.
     *
     * @return string
     */
    public static function getEmailView(): string
    {
        return preg_replace('/.*?\./', '', static::$emailView);
    }
}
