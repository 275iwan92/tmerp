{{-- Kop dokumen: logo+nama+alamat perusahaan (kiri), judul+nomor+meta dokumen
     (kanan). Isi baris meta bervariasi per dokumen (badge tipe, referensi,
     revisi, dst) -- lewat slot `meta`, bukan prop tunggal, supaya bebas HTML. --}}
@props(['company' => null, 'docTitle', 'docNo'])
<div class="print-kop">
    <div class="kop-l">
        <div class="logo">{{ strtoupper(substr($company?->company_code ?? 'TM', 0, 2)) }}</div>
        <div>
            <div class="co-name">{{ $company?->company_name ?? 'PT Telasih Makmur' }}</div>
            <div class="co-addr">
                {{ $company?->address ?? '—' }}
                @if ($company?->phone) <br>Telp {{ $company->phone }} @endif
                @if ($company?->email) · {{ $company->email }} @endif
                @if ($company?->npwp_number) · NPWP {{ $company->npwp_number }} @endif
            </div>
        </div>
    </div>
    <div class="kop-r">
        <div class="doc-title">{{ $docTitle }}</div>
        <div class="doc-no">{{ $docNo }}</div>
        <div class="doc-meta">{{ $meta ?? '' }}</div>
    </div>
</div>
