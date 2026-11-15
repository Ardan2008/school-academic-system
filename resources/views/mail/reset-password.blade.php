<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Permintaan Atur Ulang Password Disetujui</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:560px;background-color:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">

                    <tr>
                        <td style="background-color:#1d4ed8;padding:24px 32px;">
                            <p style="margin:0;font-size:18px;font-weight:700;color:#ffffff;letter-spacing:0.02em;">
                                {{ $appName }}
                            </p>
                            <p style="margin:4px 0 0;font-size:12px;color:#bfdbfe;">
                                Sistem Akademik Sekolah
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 12px;font-size:20px;line-height:1.35;color:#0f172a;">
                                Halo, {{ $userName }}
                            </h1>

                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#475569;">
                                Pengajuan atur ulang password akun <strong>{{ $roleLabel }}</strong> kamu
                                ({{ $identifierLabel }} <strong>{{ $identifier }}</strong>) sudah disetujui admin.
                            </p>

                            <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#475569;">
                                Silakan buat password baru melalui tautan di bawah. Tautan hanya berlaku satu kali
                                dan tidak bisa dipakai ulang.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:12px;background-color:#1d4ed8;">
                                        <a href="{{ $resetUrl }}"
                                           style="display:inline-block;padding:12px 24px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;">
                                            Atur Ulang Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#64748b;">
                                Tautan berlaku sampai <strong>{{ $expiresAtLabel }}</strong>.
                            </p>

                            <p style="margin:0 0 24px;font-size:12px;line-height:1.6;color:#64748b;">
                                Jika tombol tidak bisa diklik, salin tautan ini ke browser kamu:
                            </p>

                            <p style="margin:0 0 24px;font-size:12px;line-height:1.6;word-break:break-all;color:#1d4ed8;">
                                <a href="{{ $resetUrl }}" style="color:#1d4ed8;">{{ $resetUrl }}</a>
                            </p>

                            <p style="margin:0;padding:12px 16px;border-radius:12px;background-color:#fef3c7;font-size:12px;line-height:1.6;color:#92400e;">
                                Password kamu tidak pernah dikirim lewat email. Admin hanya mengirim tautan di atas.
                                Jangan bagikan tautan ini kepada siapa pun.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px;background-color:#f8fafc;border-top:1px solid #e2e8f0;">
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#94a3b8;">
                                Email ini dikirim otomatis oleh {{ $appName }}. Abaikan jika kamu tidak mengajukan
                                atur ulang password.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
