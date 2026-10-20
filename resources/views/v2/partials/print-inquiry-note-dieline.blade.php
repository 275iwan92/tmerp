{{--
 REMARK
 Tujuan       : Isi lembar lampiran "Full-Size Dieline" pada cetak Nota Inquiry: dieline tiap part dari Inquiry (skematik, tidak berskala), memindahkan bagian 4 cetak v1 pages::inquiry.nota.
 Sumber hulu  : App\Support\V2\Print\InquiryNotePrint::appendices (iqp_part.dieline_spec).
 Input        : $parts (list title, spec, dims, caption).
 Output       : x-dl::card per part, x-mp::diecut-thumb, x-dl::hint.
 Dipakai oleh : packages/devlib print-doc-appendix (Document::appendices) lewat view ini.
 Status       : Aktif (T10 pindahan v1, 2026-10-20).
--}}
@foreach ($parts as $p)
    <x-dl::card :title="raw($p['title'])">
        <x-mp::diecut-thumb :spec="$p['spec']" :dims="$p['dims']" label="Dieline" />
        <x-dl::hint>{{ $p['caption'] }}</x-dl::hint>
    </x-dl::card>
@endforeach
<x-dl::hint>@t('Schematic, not to scale. Solid line = cut, dashed line = crease / fold.')</x-dl::hint>
