{{-- Header section bernomor ("1 Penawaran Harga", "2 Spesifikasi Produk",
     dst) -- pola sama persis dipakai tiap dokumen nota, cuma nomor/judul/hint
     yang beda. Konten section tetap slot bebas (tabel/kartu/dsb spesifik
     domain, bukan tanggung jawab komponen ini). --}}
@props(['number', 'title', 'hint' => null])
<div class="print-sec">
    <div class="print-sec-h">
        <span class="n">{{ $number }}</span>
        <h3>{{ $title }}</h3>
        <span class="rule"></span>
        @if ($hint)
            <span class="hint">{{ $hint }}</span>
        @endif
    </div>
    {{ $slot }}
</div>
