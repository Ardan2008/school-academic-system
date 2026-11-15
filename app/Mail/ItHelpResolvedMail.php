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
 * Pemberitahuan "kendala sudah selesai" untuk pengaju bantuan dari pop-up
 * "Hubungi Tim IT Sekolah". Dikirim saat Admin menekan tombol Selesai pada
 * halaman Detail Kendala Akun.
 */
class ItHelpResolvedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ItHelpReply $reply,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'SASKANSA'),
            subject: 'Kendala Anda Sudah Diperbaiki · Permintaan Bantuan #'.$this->reply->itHelpRequest->id,
        );
    }

    public function content(): Content
    {
        $permintaan = $this->reply->itHelpRequest;
        $role = (string) $permintaan->role;

        return new Content(
            view: 'mail.it-help-resolved',
            with: [
                'appName' => config('app.name', 'SASKANSA'),
                'roleLabel' => ucfirst($role),
                'identifierLabel' => LoginIdentifier::label($role),
                'identifier' => $permintaan->identifier,
                'pesan' => $permintaan->pesan,
                'pesanSelesai' => $this->reply->pesan,
                'adminName' => $this->reply->user?->name ?? 'Tim IT Sekolah',
                'waktuPermintaanLabel' => $permintaan->created_at?->translatedFormat('d M Y H:i'),
                'waktuSelesaiLabel' => $this->reply->created_at?->translatedFormat('d M Y H:i'),
            ],
        );
    }
}
