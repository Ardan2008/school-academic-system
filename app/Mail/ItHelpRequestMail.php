<?php

namespace App\Mail;

use App\Models\ItHelpRequest;
use App\Support\LoginIdentifier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifikasi untuk Tim IT Sekolah setiap ada permintaan bantuan baru
 * dari pop-up "Hubungi Tim IT Sekolah" di landing page.
 */
class ItHelpRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ItHelpRequest $helpRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'SASKANSA'),
            subject: 'Permintaan Bantuan Baru dari '.ucfirst((string) $this->helpRequest->role),
        );
    }

    public function content(): Content
    {
        $role = (string) $this->helpRequest->role;

        return new Content(
            view: 'mail.it-help-request',
            with: [
                'appName' => config('app.name', 'SASKANSA'),
                'roleLabel' => ucfirst($role),
                'identifierLabel' => LoginIdentifier::label($role),
                'identifier' => $this->helpRequest->identifier,
                'email' => $this->helpRequest->email,
                'pesanPengaju' => $this->helpRequest->pesan,
                'createdAtLabel' => $this->helpRequest->created_at?->translatedFormat('d M Y H:i'),
                'ipAddress' => $this->helpRequest->ip_address ?: '-',
            ],
        );
    }
}
