<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; background:#f8fafc; padding:24px; color:#1e2433;">
    <div style="max-width:480px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;border:1px solid #e6e9ef;">
        <p style="font-size:15px;font-weight:bold;color:#2c3e69;margin:0 0 12px;">TELASIH MAKMUR — Karir</p>
        <p style="font-size:13.5px;line-height:1.6;">Halo {{ $candidateName }},</p>
        <p style="font-size:13.5px;line-height:1.6;">Terima kasih sudah mendaftar di portal karir kami. Klik tombol di bawah untuk verifikasi email Anda:</p>
        <p style="text-align:center;margin:24px 0;">
            <a href="{{ $verifyUrl }}" style="background:#2f6fed;color:#fff;text-decoration:none;font-weight:bold;font-size:13px;padding:12px 24px;border-radius:8px;display:inline-block;">Verifikasi Email</a>
        </p>
        <p style="font-size:11.5px;color:#6b7280;line-height:1.6;">Kalau tombol tidak berfungsi, salin tautan berikut ke browser:<br>{{ $verifyUrl }}</p>
        <p style="font-size:11.5px;color:#6b7280;margin-top:20px;">Tautan berlaku 60 menit. Kalau Anda tidak merasa mendaftar, abaikan email ini.</p>
    </div>
</body>
</html>
