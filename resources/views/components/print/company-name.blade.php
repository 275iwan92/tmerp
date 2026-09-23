{{-- Nama perusahaan di dokumen cetak (keputusan user 2026-09-23, backlog01 UI-09).
     Non PPN / default = COMPANY_NAME ("Telasih Makmur"); dokumen ber-PPN yang
     dicetak "PPN" = COMPANY_LEGAL_NAME ("CV. Langgeng Jaya Makmur"). Pilihannya
     state Alpine ppnKop milik x-print.document (:ppn=true). --}}
@props(['ppn' => false])
@if ($ppn)
    <span x-show="! ppnKop">{{ \App\Support\Branding\Brand::companyName() }}</span><span x-show="ppnKop" x-cloak>{{ \App\Support\Branding\Brand::companyLegalName() }}</span>
@else
    {{ \App\Support\Branding\Brand::companyName() }}
@endif
