<!DOCTYPE html>
<html lang="{{ \Devlib\Locale::lang() }}">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; background:#f8fafc; padding:24px; color:#1e2433;">
    <div style="max-width:480px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;border:1px solid #e6e9ef;">
        <p style="font-size:15px;font-weight:bold;color:#2c3e69;margin:0 0 12px;">TELASIH MAKMUR — {{ t('Careers') }}</p>
        <p style="font-size:13.5px;line-height:1.6;">{{ str_replace(':name', $candidateName, t('Hello :name,')) }}</p>
        <p style="font-size:13.5px;line-height:1.6;">{{ t('Thank you for registering on our careers portal. Click the button below to verify your email:') }}</p>
        <p style="text-align:center;margin:24px 0;">
            <a href="{{ $verifyUrl }}" style="background:#2f6fed;color:#fff;text-decoration:none;font-weight:bold;font-size:13px;padding:12px 24px;border-radius:8px;display:inline-block;">{{ t('Verify Email') }}</a>
        </p>
        <p style="font-size:11.5px;color:#6b7280;line-height:1.6;">{{ t('If the button does not work, copy the following link into your browser:') }}<br>{{ $verifyUrl }}</p>
        <p style="font-size:11.5px;color:#6b7280;margin-top:20px;">{{ t('The link is valid for 60 minutes. If you did not register, ignore this email.') }}</p>
    </div>
</body>
</html>
