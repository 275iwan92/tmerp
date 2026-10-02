{{-- Kop dokumen: logo+nama+alamat perusahaan (kiri), judul+nomor+meta dokumen
     (kanan). Isi baris meta bervariasi per dokumen (badge tipe, referensi,
     revisi, dst) -- lewat slot `meta`, bukan prop tunggal, supaya bebas HTML.

     Nama (keputusan user 2026-09-23, backlog01 UI-09): SELALU nama perusahaan
     (konstanta COMPANY_NAME). Dokumen ber-PPN (:ppn=true, pasangan prop sama di
     x-print.document) bisa dicetak "PPN" = nama terdaftar (COMPANY_LEGAL_NAME)
     + NPWP; pilihannya state Alpine ppnKop milik x-print.document. --}}
@props(['company' => null, 'docTitle', 'docNo', 'ppn' => false])
@php($legalName = \App\Support\Branding\Brand::companyLegalName())
{{-- Logo perusahaan (user 2026-10-02, App\Support\Branding\CompanyLogo): gambarnya sudah memuat nama, jadi menggantikan
     kotak inisial + nama pada kop Telasih Makmur. Kop "PPN" (badan hukum) tetap kotak inisial + nama terdaftar. --}}
@php($logoUrl = \App\Support\Branding\CompanyLogo::url())
<div class="print-kop">
    <div class="kop-l">
        @if (! $logoUrl)
            <div class="logo">{{ strtoupper(substr($company?->company_code ?? 'TM', 0, 2)) }}</div>
        @elseif ($ppn)
            <div class="logo" x-show="ppnKop" x-cloak>{{ strtoupper(substr($company?->company_code ?? 'TM', 0, 2)) }}</div>
        @endif
        <div>
            @if (! $logoUrl)
                <div class="co-name"><x-print.company-name :ppn="$ppn" /></div>
            @else
                <img class="co-logo" src="{{ $logoUrl }}" alt="{{ \App\Support\Branding\Brand::companyName() }}" @if ($ppn) x-show="! ppnKop" @endif>
                @if ($ppn)<div class="co-name" x-show="ppnKop" x-cloak>{{ $legalName }}</div>@endif
            @endif
            <div class="co-addr">
                {!! nl2br(e($company?->address ?? '—')) !!}
                @if ($company?->phone) <br>{{ t('Tel.') }} {{ $company->phone }} @endif
                @if ($company?->email) · {{ $company->email }} @endif
            </div>
            @if ($ppn)
                <div class="co-npwp" x-show="ppnKop" x-cloak>NPWP {{ $company?->npwp_number ?? \App\Support\Branding\Brand::companyNpwp() ?? '—' }} · {{ t('registered to') }} {{ $legalName }}</div>
            @endif
        </div>
    </div>
    <div class="kop-r">
        <div class="doc-title">{{ $docTitle }}</div>
        <div class="doc-no">{{ $docNo }}</div>
        <div class="doc-meta">{{ $meta ?? '' }}</div>
    </div>
</div>
