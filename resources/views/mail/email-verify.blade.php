<!DOCTYPE html>
<html lang="id">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:10px;padding:24px">
        <p style="margin:0 0 4px;font-size:12px;color:#6b7280">{{ $company }}</p>
        <h2 style="margin:0 0 16px;font-size:18px">Verifikasi e-mail</h2>
        <p style="font-size:14px;line-height:1.55">Halo {{ $userName }}, klik tombol di bawah untuk memastikan alamat ini milik Anda. Sesudah terverifikasi, dokumen yang Anda kirim dari aplikasi memakai alamat ini.</p>
        <p style="margin:18px 0"><a href="{{ $url }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px">Verifikasi e-mail saya</a></p>
        <p style="font-size:11px;color:#9ca3af;margin:14px 0 0">Link berlaku 24 jam. Abaikan e-mail ini bila Anda tidak memintanya.</p>
    </div>
</body>
</html>
