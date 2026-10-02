<!DOCTYPE html>
{{-- Dokumen online hasil Print/Email (route doc.share, publik). Isi = snapshot saat dicetak; devlib.js SENGAJA tidak dimuat
     (snapshot statis, perilaku komponen tidak dijalankan ulang). Hanya skrip ber-nonce di bawah yang boleh jalan (CSP di route). --}}
<html lang="{{ \Devlib\Locale::lang() }}" data-dl-theme="{{ \Devlib\Theme::DEFAULT }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $share->title }}{{ $share->doc_no ? ' · '.$share->doc_no : '' }} — {{ \App\Support\Branding\Brand::companyName() }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ \Devlib\DevlibServiceProvider::asset('devlib.css') }}">
    <style>{!! \Devlib\Theme::css() !!}</style>
    <link rel="stylesheet" href="{{ \MockupParts\MockupPartsServiceProvider::asset('mockup-parts.css') }}">
</head>
<body class="dl-body dl-share-page">
    <header class="dl-share-page__head">
        <div>
            <small>{{ \App\Support\Branding\Brand::companyName() }} · {{ t('online document') }}</small>
            <b>{{ $share->title }}@if ($share->doc_no) · {{ $share->doc_no }}@endif</b>
            <span>{{ str_replace(':at', \Devlib\Locale::dateTime($share->created_at), t('Copy as printed :at')) }}{{ $share->created_by ? ' '.str_replace(':user', $share->created_by, t('by :user')) : '' }}@if ($share->expires_at) · {{ str_replace(':date', \Devlib\Locale::date($share->expires_at, 'long'), t('valid until :date')) }}@endif</span>
        </div>
        <div class="dl-share-page__qr" id="dl-share-qr" title="{{ $share->url() }}"></div>
        <button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" id="dl-share-print"><span class="dl-btn__icon"><i class="fa-solid fa-print"></i></span>Print</button>
    </header>
    <main class="dl-share-page__doc">{!! $share->html !!}</main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" nonce="{{ $nonce }}"></script>
    <script nonce="{{ $nonce }}">
        new QRCode(document.getElementById('dl-share-qr'), {text: @json($share->url()), width: 84, height: 84, correctLevel: QRCode.CorrectLevel.M});
        document.getElementById('dl-share-print').addEventListener('click', () => window.print());
        // Cetakan dokumen (x-dl::print-doc): kotak QR di lembar diisi tautan salinan online ini.
        document.querySelectorAll('.dl-share-page__doc [data-pd-qr]').forEach(e => { e.innerHTML = ''; new QRCode(e, {text: @json($share->url()), width: 96, height: 96, correctLevel: QRCode.CorrectLevel.M}); e.classList.add('is-on'); });
        document.querySelectorAll('.dl-share-page__doc input, .dl-share-page__doc select, .dl-share-page__doc textarea, .dl-share-page__doc button').forEach(e => e.disabled = true);
    </script>
</body>
</html>
