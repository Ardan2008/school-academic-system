<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email yang dikirim setelah Admin menyetujui pengajuan atur ulang password.
 *
 * Email ini hanya memuat tautan bertoken, tidak pernah memuat password baru.
 */
class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $roleLabel,
        public readonly string $identifierLabel,
        public readonly string $identifier,
        public readonly string $resetUrl,
        public readonly string $expiresAtLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'SASKANSA'),
            subject: 'Permintaan Atur Ulang Password SASKANSA Disetujui',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.reset-password',
            with: [
                'appName' => config('app.name', 'SASKANSA'),
            ],
        );
    }
}
