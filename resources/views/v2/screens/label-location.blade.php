{{-- Print Location Label (user 2026-10-02: "Facility for QR Code, Labelling") lewat App\Support\V2\Data\Labels: pilih gedung ->
     pilih bin (kosong = tidak ada yang dicetak; tombol "all bins of an area" lewat pilihan area) -> ukuran + jumlah lembar
     -> Print: riwayat dicatat (Label Print History) lalu lembar label terbuka. Label = kode bin, area, kapasitas, QR + barcode. --}}
@php
    use App\Support\V2\Data\Labels;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

{{-- Form = objek form khusus x-mp::inv.label-print (MockupParts), dipakai juga layar label lainnya; isi = Labels::locationForm(). --}}
<x-mp::inv.label-print :form="Labels::locationForm(route('v2.screen', 'label-location'))" />
