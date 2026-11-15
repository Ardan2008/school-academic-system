<?php

namespace Tests\Feature;

use App\Mail\ItHelpReplyMail;
use App\Mail\ItHelpRequestMail;
use App\Mail\ItHelpResolvedMail;
use App\Mail\ResetPasswordMail;
use App\Models\ItHelpReply;
use App\Models\ItHelpRequest;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSenderNameTest extends TestCase
{
    public function test_config_mail_from_name_is_saskansa(): void
    {
        $this->assertSame('SASKANSA', config('mail.from.name'));
    }

    public function test_semua_mailable_memakai_nama_pengirim_saskansa(): void
    {
        $permintaan = $this->buatPermintaan();
        $balasan = $this->buatBalasan($permintaan);

        $mailables = [
            new ItHelpRequestMail($permintaan),
            new ItHelpReplyMail($balasan),
            new ItHelpResolvedMail($balasan),
            new ResetPasswordMail('Nama', 'Guru', 'NIP', 'G-1', 'https://saskansa.test/reset', 'besok'),
        ];

        foreach ($mailables as $mailable) {
            $from = $mailable->envelope()->from;

            $this->assertNotNull($from);
            $this->assertSame('SASKANSA', $from->name);
            $this->assertSame(config('mail.from.address'), $from->address);
        }
    }

    public function test_header_from_email_yang_dikirim_adalah_saskansa(): void
    {
        $permintaan = $this->buatPermintaan();
        $transport = Mail::mailer('array')->getSymfonyTransport();

        Mail::mailer('array')->to('pengaju@example.com')->send(new ItHelpRequestMail($permintaan));

        $sent = $transport->messages()->first();
        $from = $sent->getOriginalMessage()->getFrom();

        $this->assertCount(1, $from);
        $this->assertSame('SASKANSA', $from[0]->getName());
        $this->assertSame(config('mail.from.address'), $from[0]->getAddress());
    }

    private function buatPermintaan(): ItHelpRequest
    {
        $permintaan = new ItHelpRequest([
            'role' => 'guru',
            'identifier' => 'G-1',
            'email' => 'pengaju@example.com',
            'pesan' => 'Tidak bisa masuk.',
            'status' => ItHelpRequest::STATUS_BARU,
        ]);
        $permintaan->id = 1;

        return $permintaan;
    }

    private function buatBalasan(ItHelpRequest $permintaan): ItHelpReply
    {
        $balasan = new ItHelpReply([
            'pesan' => 'Sudah kami perbaiki.',
            'status_pengiriman' => 'terkirim',
        ]);
        $balasan->setRelation('itHelpRequest', $permintaan);
        $balasan->setRelation('user', null);

        return $balasan;
    }
}
