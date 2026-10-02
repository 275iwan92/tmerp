<!DOCTYPE html>
<html lang="{{ \Devlib\Locale::lang() }}">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:10px;padding:24px">
        <p style="margin:0 0 4px;font-size:12px;color:#6b7280">{{ $company }}</p>
        <h2 style="margin:0 0 16px;font-size:18px">{{ t('E-mail verification') }}</h2>
        <p style="font-size:14px;line-height:1.55">{{ str_replace(':name', $userName, t('Hello :name, click the button below to confirm this address is yours.')) }} {{ t('Once verified, documents you send from the application use this address.') }}</p>
        <p style="margin:18px 0"><a href="{{ $url }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px">{{ t('Verify my e-mail') }}</a></p>
        <p style="font-size:11px;color:#9ca3af;margin:14px 0 0">{{ t('The link is valid for 24 hours. Ignore this e-mail if you did not request it.') }}</p>
    </div>
</body>
</html>
