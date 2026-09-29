<!DOCTYPE html>
<html lang="id">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">
    <div style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:10px;padding:24px">
        <p style="margin:0 0 4px;font-size:12px;color:#6b7280">{{ $company }}</p>
        <h2 style="margin:0 0 16px;font-size:18px">{{ $share->title }}@if ($share->doc_no) · {{ $share->doc_no }}@endif</h2>
        @if ($body)<div style="font-size:14px;line-height:1.55;margin-bottom:18px">{!! nl2br(e($body)) !!}</div>@endif
        @if ($withLink)
            <p style="margin:0 0 18px"><a href="{{ $share->url() }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px">Buka dokumen online</a></p>
        @endif
        @if ($image)
            <img src="{{ $message->embedData($image, 'dokumen.jpg', 'image/jpeg') }}" alt="{{ $share->title }}" style="width:100%;border:1px solid #e5e7eb;border-radius:6px">
        @endif
        @if ($pdf)<p style="font-size:12px;color:#6b7280;margin:14px 0 0">Dokumen PDF terlampir.</p>@endif
        @if ($withLink)<p style="font-size:11px;color:#9ca3af;margin:14px 0 0">{{ $share->url() }}</p>@endif
    </div>
</body>
</html>
