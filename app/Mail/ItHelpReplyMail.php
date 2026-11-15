<?php

namespace App\Mail;

use App\Models\ItHelpReply;
use App\Support\LoginIdentifier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Balasan Admin untuk pengguna yang mengirim permintaan bantuan
 * lewat pop-up "Hubungi Tim IT Sekolah" di landing page.
 */
class ItHelpReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ItHelpReply $reply,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'SASKANSA'),
            subject: 'Tindak Lanjut Permintaan Bantuan #'.$this->reply->itHelpRequest->id,
        );
    }

    public function content(): Content
    {
        $permintaan = $this->reply->itHelpRequest;
        $role = (string) $permintaan->role;

        return new Content(
            view: 'mail.it-help-reply',
            with: [
                'appName' => config('app.name', 'SASKANSA'),
                'roleLabel' => ucfirst($role),
                'identifierLabel' => LoginIdentifier::label($role),
                'identifier' => $permintaan->identifier,
                'pesan' => $permintaan->pesan,
                'balasan' => $this->reply->pesan,
                'adminName' => $this->reply->user?->name ?? 'Tim IT Sekolah',
                'waktuPermintaanLabel' => $permintaan->created_at?->translatedFormat('d M Y H:i'),
            ],
        );
    }
}
