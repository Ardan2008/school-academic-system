<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Tindak Lanjut Permintaan Bantuan {{ $appName }}</title>
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
                                Tindak Lanjut Permintaan Bantuan Anda
                            </h1>

                            <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#475569;">
                                Permintaan bantuan yang Anda kirim melalui tombol
                                <strong>Hubungi Tim IT Sekolah</strong> sudah kami terima
                                dan ditindaklanjuti oleh {{ $adminName }}.
                            </p>

                            <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;text-transform:uppercase;color:#94a3b8;">
                                Ringkasan Permintaan
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b;">
                                        {{ $identifierLabel }}
                                    </td>
                                    <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;font-weight:600;">
                                        {{ $identifier }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b;">
                                        Role
                                    </td>
                                    <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;font-weight:600;">
                                        {{ $roleLabel }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;font-size:13px;color:#64748b;">
                                        Waktu Pengajuan
                                    </td>
                                    <td style="padding:10px 0;font-size:14px;color:#0f172a;font-weight:600;">
                                        {{ $waktuPermintaanLabel ?? '-' }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;text-transform:uppercase;color:#94a3b8;">
                                Isi Kendala Anda
                            </p>

                            <p style="margin:0 0 20px;padding:14px 16px;border-radius:12px;background-color:#f8fafc;font-size:14px;line-height:1.7;color:#475569;white-space:pre-line;">
                                {{ $pesan }}
                            </p>

                            <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;text-transform:uppercase;color:#94a3b8;">
                                Tanggapan / Solusi
                            </p>

                            <p style="margin:0;padding:14px 16px;border-radius:12px;border:1px solid #bfdbfe;background-color:#eff6ff;font-size:14px;line-height:1.7;color:#0f172a;white-space:pre-line;">
                                {{ $balasan }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px;background-color:#f8fafc;border-top:1px solid #e2e8f0;">
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#94a3b8;">
                                Email ini dikirim otomatis oleh {{ $appName }}
                                sebagai tindak lanjut permintaan bantuan Anda.
                                Jika kendala belum terselesaikan, balas email ini
                                pada hari dan jam kerja.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
