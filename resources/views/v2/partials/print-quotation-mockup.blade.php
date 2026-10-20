{{--
 REMARK
 Tujuan       : Isi lembar lampiran "Product Mockup" pada cetak Quotation: tiap part produk dari Inquiry ditampilkan dengan dieline dan pratinjau 3D (ilustrasi, bukan proof warna), memindahkan lampiran cetak v1 pages::quotation.print.
 Sumber hulu  : App\Support\V2\Print\QuotationPrint::appendices (mockupParts: iqp_part.dieline_spec + artwork_path).
 Input        : $parts (list judul, type, size, spec, dims, box p/l/t dalam cm, artwork).
 Output       : x-dl::card per part, x-mp::diecut-thumb, x-mp::box3d, x-dl::hint.
 Dipakai oleh : packages/devlib print-doc-appendix (Document::appendices) lewat view ini.
 Status       : Aktif (T10 pindahan v1, 2026-10-20).
--}}
@foreach ($parts as $p)
    <x-dl::card :title="raw($p['title'])" :description="trim($p['type'].($p['size'] ? ' · '.$p['size'].' mm' : ''))">
        <x-dl::form.row :cols="2">
            <x-mp::diecut-thumb :spec="$p['spec']" :dims="$p['dims']" label="Dieline" />
            <x-mp::box3d :dims="$p['box']" :front="$p['artwork']" />
        </x-dl::form.row>
        @if (! $p['spec'])
            <x-dl::hint>@t('The dieline is not in the Inquiry yet.')</x-dl::hint>
        @elseif (! $p['artwork'])
            <x-dl::hint>@t('The artwork is not uploaded in the Inquiry yet — only the dieline lines are shown.')</x-dl::hint>
        @endif
    </x-dl::card>
@endforeach
<x-dl::hint>@t('Illustration from the Inquiry Product, not a colour proof.')</x-dl::hint>
